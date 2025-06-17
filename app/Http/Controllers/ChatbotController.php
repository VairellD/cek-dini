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
    public function startNewConversation()
    {
        $conversation = Conversation::create([
            'user_id' => Auth::id(),
            'title' => 'Conversation on ' . now()->format('F j, Y, g:i a')
        ]);

        // Create the initial greeting from the bot
        $conversation->messages()->create([
            'sender' => 'bot',
            'content' => "Hello! I'm Dini, an AI assistant designed to provide information about breast cancer symptoms and risk factors. How can I help you today? \n\nPlease remember, I am not a medical professional, and this conversation is not a substitute for a medical diagnosis."
        ]);

        return redirect()->route('chatbot.show', $conversation);
    }

    /**
     * This is now the main method that powers the entire conversation.
     */

    // In app/Http/Controllers/ChatbotController.php
    public function ask(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);

        $userInput = $request->input('message');

        // 1. Save the user's new message to the database
        $conversation->messages()->create([
            'sender' => 'user',
            'content' => $userInput
        ]);

        // 2. Prepare the conversation history, excluding the latest user input to avoid duplication
        $history = $conversation->messages()
            ->where('id', '!=', $conversation->messages()->latest()->first()->id) // Exclude the latest message
            ->latest()
            ->take(14) // Take 14 to leave room for the latest user input
            ->get()
            ->reverse()
            ->map(function ($msg) {
                Log::debug('Content Parse Input', [
                    'part' => $msg->content,
                    'part_type' => gettype($msg->content),
                    'role' => $msg->sender === 'user' ? Role::USER : Role::MODEL,
                    'role_type' => gettype($msg->sender === 'user' ? Role::USER : Role::MODEL)
                ]);
                return Content::parse(
                    part: is_string($msg->content) ? $msg->content : (string)$msg->content,
                    role: $msg->sender === 'user' ? Role::USER : Role::MODEL
                );
            })->values()->toArray();

        // Add the latest user input explicitly
        $history[] = Content::parse(
            part: $userInput,
            role: Role::USER
        );

        // 3. Call the Gemini API with our carefully crafted instructions and history
        try {
            Log::debug('Gemini API Request', [
                'model' => 'gemini-2.0-flash',
                'system_prompt' => $this->getSystemPrompt(),
                'history' => $history,
                'user_input' => $userInput
            ]);
            $response = Gemini::generativeModel(model: 'gemini-2.0-flash')
                ->generateContent($this->getSystemPrompt(), ...$history);
            $botReply = $response->text() ?? "No response generated from the API.";
            Log::debug('Gemini API Response', ['response' => $botReply]);
        } catch (\Exception $e) {
            Log::error("Gemini API Error: " . $e->getMessage(), [
                'conversation_id' => $conversation->id,
                'user_input' => $userInput,
                'exception' => $e->getTraceAsString(),
                'api_key' => config('gemini.api_key'),
                'model' => 'gemini-2.0-flash'
            ]);
            $botReply = "I apologize, I'm encountering a technical issue at the moment. Please try again in a few moments.";
        }

        // 4. Save the bot's response to the database
        $conversation->messages()->create([
            'sender' => 'bot',
            'content' => $botReply
        ]);

        // 5. Return the bot's response to the frontend
        return response()->json([
            'message' => $botReply
        ]);
    }


    /**
     * THE MOST IMPORTANT PART: The System Prompt
     * This "constitution" sets the rules, persona, and boundaries for our AI assistant.
     */
    private function getSystemPrompt(): string
    {
        return <<<PROMPT
        You are "Dini", a specialized AI assistant. Your persona is empathetic, calm, clear, and safe. You are designed to be a Breast Cancer Information and Symptom Checker Assistant.

        Your Core Directives:
        1.  Your primary goal is to help users understand breast cancer symptoms and risk factors.
        2.  Your ultimate objective is to STRONGLY and CONSISTENTLY encourage users to consult a real healthcare professional.

        CRITICAL SAFETY RULES - YOU MUST OBEY THESE AT ALL TIMES:
        -   DO NOT, under any circumstances, provide a diagnosis, a probability, a guess, or any form of medical opinion on whether the user has cancer.
        -   DO NOT use conclusive or diagnostic-sounding language (e.g., "It sounds like you have...", "This is likely...", "You might have...").
        -   Instead of diagnosing, explain what a symptom means in a general sense and state that it requires professional evaluation. For example, if a user describes a lump, say: "A new lump in the breast is a symptom that should always be evaluated by a doctor to determine its cause."
        -   If a user directly asks "Do I have cancer?" or "Should I be worried?", you MUST respond with a variation of: "I cannot answer that, as I am an AI assistant and not a medical professional. It's very important to discuss your symptoms and concerns with a doctor who can give you an accurate diagnosis and proper guidance."
        -   Keep your responses concise and easy to understand. Avoid overly technical jargon.
        -   Always end conversations by reinforcing the importance of a professional medical consultation.
        -   IF someone ASKED YOU OTHER THINGS AND NOT ABOUT BREAST OR BREAST CANCER always answer with "I Cant Answer thing Not Related To Breast Cancer"
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
