<?php

use App\Http\Controllers\Auth\AuthGoogleController;
use App\Http\Controllers\ChatbotController;
use App\Models\Conversation; // Import the model
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('/auth')->group(function () {
    Route::get('google', [AuthGoogleController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('google/callback', [AuthGoogleController::class, 'handleGoogleCallback']);
});

Route::get('/dashboard', [ChatbotController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
Route::middleware(['auth'])->group(function () {
    // A new route to show a list of past conversations
    Route::get('/chatbot', [ChatbotController::class, 'index'])->name('chatbot.index');

    // Route to start a NEW chat session
    Route::post('/chatbot', [ChatbotController::class, 'startNewConversation'])->name('chatbot.start');

    // Route to view and interact with a SPECIFIC conversation
    Route::get('/chatbot/{conversation}', [ChatbotController::class, 'show'])->name('chatbot.show');

    // Route to send a message within a specific conversation
    Route::post('/chatbot/{conversation}/ask', [ChatbotController::class, 'ask'])->name('chatbot.ask');

    Route::get('/listmodels', [ChatbotController::class, 'listModels']);
});

require __DIR__ . '/auth.php';
