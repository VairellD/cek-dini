<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            // Link to the conversation this message belongs to
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            // 'user' or 'bot'
            $table->enum('sender', ['user', 'bot']);
            // The actual message content
            $table->text('content');
            // We can also store the raw data for the prediction questions
            $table->json('context_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
