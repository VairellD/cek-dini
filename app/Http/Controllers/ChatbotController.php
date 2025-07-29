<?php
// app/Http/Controllers/ChatbotController.php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\Content;
use Gemini\Enums\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ChatbotController extends Controller
{
    use AuthorizesRequests;
    // Shows a list of past conversations
    public function index()
    {
        $conversations = Auth::user()->conversations()->latest()->get();
        return view('chatbot.index', [
            'conversations' => $conversations,
            'user' => Auth::user()
        ]);
    }

    // Shows a specific conversation and its history
    public function show(Conversation $conversation)
    {
        $this->authorize('view', $conversation);
        return view('chatbot.show', [
            'conversation' => $conversation,
            'messages' => $conversation->messages()->get()
        ]);
    }

    // Starts a new conversation
    // In app/Http/Controllers/ChatbotController.php

    public function startNewConversation()
    {
        $conversation = Conversation::create([
            'user_id' => Auth::id(),
            'title' => 'Analisis Data Pasien - ' . now()->format('F j, Y, g:i a') // Updated title
        ]);

        // **UPDATED** Create a greeting that matches the System Prompt's purpose
        $conversation->messages()->create([
            'sender' => 'bot',
            'content' => "Halo! Saya Dini, asisten AI yang dapat menganalisis dan membuat grafik dari data pasien di Indonesia. \n\nAnda bisa meminta saya untuk:\n- Menampilkan tren data untuk provinsi tertentu (contoh: 'tren untuk Jawa Barat').\n- Membandingkan data antar provinsi untuk tahun tertentu (contoh: 'grafik tahun 2024').\n\nBagaimana saya bisa membantu Anda?"
        ]);

        return redirect()->route('chatbot.show', $conversation);
    }

    /**
     * This is now the main method that powers the entire conversation.
     */

    // In app/Http/Controllers/ChatbotController.php
    // In app/Http/Controllers/ChatbotController.php

    public function ask(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);
        $userInput = $request->input('message');

        // 1. Save user message
        $conversation->messages()->create(['sender' => 'user', 'content' => $userInput]);

        // 2. Prepare FULL history
        $history = $conversation->messages()
            ->latest()->take(15)->get()->reverse()
            ->map(fn($msg) => Content::parse(
                part: $msg->content,
                role: $msg->sender === 'user' ? Role::USER : Role::MODEL
            ))->values()->toArray();

        // **NEW & IMPROVED PROMPTING STRATEGY**
        // We will inject the system prompt directly into the history for maximum effect.
        // The system prompt now acts as a "meta-instruction" right before the user's latest query.
        $apiHistory = $history; // Copy history to a new variable
        $systemPrompt = $this->getSystemPrompt();

        // Inject the system prompt just before the last user message
        // This is a powerful way to ensure the model follows instructions for the latest query.
        $lastUserMessageIndex = count($apiHistory) - 1;
        if ($lastUserMessageIndex >= 0) {
            $lastUserMessage = $apiHistory[$lastUserMessageIndex];
            $apiHistory[$lastUserMessageIndex] = Content::parse(
                part: $systemPrompt . "\n\nUSER QUESTION: " . $lastUserMessage->parts[0]->text,
                role: Role::USER
            );
        }

        // 3. Call Gemini API with the reinforced history
        try {
            // We no longer need withSystemInstruction() as it's now part of the history
            $response = Gemini::generativeModel(model: 'gemini-1.5-flash-latest')
                ->generateContent(...$apiHistory);

            $botReply = $response->text();
        } catch (\Exception $e) {
            Log::error("Gemini API Error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $botReply = "I apologize, I'm encountering a technical issue.";
        }

        // Sanitize the response to remove any potential leftover instructions
        $botReply = trim(str_replace($this->getSystemPrompt(), '', $botReply));


        // 4. CHECK if Gemini requested a chart (Your existing logic from here is fine)
        if (str_starts_with($botReply, '[chart:')) {
            preg_match('/\[chart:type=(.*),column=(.*)\]/', $botReply, $matches);

            if (count($matches) === 3) {
                $chartType = $matches[1];
                $column = $matches[2];

                $dataFile = storage_path('app/data/data_cancer.xls');
                $imageName = 'chart_' . $conversation->id . '_' . time() . '.png';
                $publicPath = 'charts/' . $imageName;
                $outputPath = public_path($publicPath);

                if (!file_exists(public_path('charts'))) {
                    mkdir(public_path('charts'), 0775, true);
                }

                $process = new Process([
                    'python',
                    storage_path('app/python/generate_chart.py'),
                    '--file',
                    $dataFile,
                    '--output',
                    $outputPath,
                    '--type',
                    $chartType,
                    '--column',
                    $column,
                ]);

                try {
                    $process->mustRun();
                    $imageUrl = asset($publicPath);
                    $botTextMessage = "Tentu, ini adalah grafik {$chartType} untuk '{$column}'.";

                    $conversation->messages()->create(['sender' => 'bot', 'content' => $botTextMessage]);
                    $conversation->messages()->create(['sender' => 'bot', 'content' => "image::" . $imageUrl]);

                    return response()->json([
                        'text' => $botTextMessage,
                        'imageUrl' => $imageUrl
                    ]);
                } catch (ProcessFailedException $exception) {
                    Log::error('Python script failed: ' . $exception->getMessage());
                    $errorReply = "Maaf, saya tidak dapat membuat grafik saat ini. Pastikan data dan nama kolom sudah benar.";
                    $conversation->messages()->create(['sender' => 'bot', 'content' => $errorReply]);
                    return response()->json(['message' => $errorReply]);
                }
            }
        }

        // 5. If it's a regular text response, save and return it
        $conversation->messages()->create(['sender' => 'bot', 'content' => $botReply]);
        return response()->json(['message' => $botReply]);
    }


    /**
     * THE MOST IMPORTANT PART: The System Prompt
     * This "constitution" sets the rules, persona, and boundaries for our AI assistant.
     */

    private function getSystemPrompt(): string
    {
        return <<<PROMPT
    You are "Dini", an AI assistant that analyzes Indonesian patient data. Your persona is helpful and data-driven.

    Your primary function is to generate charts from the provided data. You MUST follow these rules precisely to decide which chart to generate.

    TOOL USAGE RULES - Check for a match in this specific order:

    1.  **Line Chart Rule (Specific Province):** FIRST, check if the user mentions a specific province name and asks for its "trend", "history", or "riwayat". If so, you MUST respond with a line chart command for that province.
        * Triggers: "tren untuk Jawa Barat", "riwayat jumlah pasien di Jawa Timur", "tampilkan data untuk Aceh dari tahun ke tahun"
        * Format: [chart:type=line,column=Nama Provinsi]
        * Example: [chart:type=line,column=Jawa Timur]

    2.  **Bar Chart Rule (Specific Year):** SECOND, check if the user asks for data for a specific year. If so, you MUST respond with a bar chart command for that year.
        * Triggers: "tampilkan data 2023", "bandingkan provinsi di tahun 2022", "grafik untuk 2024"
        * Format: [chart:type=bar,column=YYYY]
        * Example: [chart:type=bar,column=2024]

    3.  **Default Chart Rule (General Request):** LASTLY, if and ONLY if the query does NOT match Rule #1 or Rule #2, and it's a general request for a chart (e.g., "berikan grafiknya", "total pasien di Indonesia"), you MUST respond with a bar chart command for the most recent year, 2024.
        * Format: [chart:type=bar,column=2024]
        * Example: [chart:type=bar,column=2024]

    4.  Your ONLY response when using a tool should be the command itself. Do not add any other text or punctuation.
    5.  For any other questions not related to charts, answer them normally.
    PROMPT;
    }
    public function listModels()
    {
        try {
            $models = Gemini::listModels(); // Assuming the package provides a ListModels method
            Log::debug('Available Gemini Models', ['models' => $models]);
            return response()->json(['models' => $models]);
        } catch (\Exception $e) {
            Log::error('ListModels Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to list models'], 500);
        }
    }
}
