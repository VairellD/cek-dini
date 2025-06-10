<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    @section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <a href="{{ route('chatbot.index') }}" class="btn btn-sm btn-outline-secondary me-2">← Back</a>
                        <span>{{ $conversation->title }}</span>
                    </div>

                    {{-- The Chat Window --}}
                    <div class="card-body" id="chat-box" style="height: 400px; overflow-y: scroll;">
                        @foreach($messages as $message)
                            <div
                                class="message mb-3 p-2 rounded {{ $message->sender === 'user' ? 'bg-light' : 'bg-primary-subtle' }}">
                                {!! nl2br(e($message->content)) !!}
                            </div>
                        @endforeach
                    </div>

                    {{-- The Input Form --}}
                    <div class="card-footer">
                        <form id="chat-form" action="{{ route('chatbot.ask', $conversation) }}" method="POST"
                            autocomplete="off">
                            @csrf
                            <div class="input-group">
                                <input type="text" id="user-input" class="form-control"
                                    placeholder="Type your message here..." required>
                                <button class="btn btn-primary" type="submit" id="send-button">Send</button>
                            </div>
                            <div id="input-hint" class="form-text text-muted mt-1" style="height: 1rem;"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // The Javascript can be simplified. 
        // It no longer needs to ask the first question, as the controller handles that.
        // Its only job is to take user input, send it via fetch, and display the response.
        // The fetch URL should be `chatForm.action`.

        // Inside resources/views/chatbot/show.blade.php

        // ... inside the <script> tag ...
        document.addEventListener('DOMContentLoaded', function () {
            const chatForm = document.getElementById('chat-form');
            const userInput = document.getElementById('user-input');
            const chatBox = document.getElementById('chat-box');
            const inputHint = document.getElementById('input-hint');
            const sendButton = document.getElementById('send-button');

            // Automatically scroll to the bottom of the chat box
            chatBox.scrollTop = chatBox.scrollHeight;

            // Function to add a new message to the chat display
            function addMessage(message, sender) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message mb-3 p-2 rounded ${sender === 'user' ? 'bg-light' : 'bg-primary-subtle'}`;
                // Use innerText to prevent HTML injection from bot response
                messageDiv.innerText = message;
                chatBox.appendChild(messageDiv);
                chatBox.scrollTop = chatBox.scrollHeight; // Auto-scroll
            }

            // =======================================================
            // THIS IS THE CORE SEND/RECEIVE LOGIC
            // =======================================================
            chatForm.addEventListener('submit', async function (e) {
                e.preventDefault(); // Prevent the form from doing a full page reload
                const message = userInput.value.trim();
                if (!message) return;

                // --- PART 1: SENDING ---
                addMessage(message, 'user'); // Visually add the user's message immediately
                userInput.value = ''; // Clear the input field
                inputHint.textContent = 'Aura is thinking...'; // Show a thinking indicator
                sendButton.disabled = true; // Disable the button to prevent double-sending

                try {
                    // This is the actual network request that SENDS the message to Laravel
                    const response = await fetch(chatForm.action, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                        },
                        body: JSON.stringify({ message: message }) // The data being sent
                    });

                    // --- PART 2: RECEIVING ---
                    // We wait for the server to process and respond
                    const data = await response.json();

                    // When the response is RECEIVED, display the bot's message
                    if (data.message) {
                        addMessage(data.message, 'bot');
                    } else {
                        addMessage('Sorry, there was an error processing your request.', 'bot');
                    }
                } catch (error) {
                    console.log('Error:', error);

                    addMessage('Could not connect to the server. Please check your connection.', 'bot');
                } finally {
                    // Re-enable the form regardless of success or failure
                    inputHint.textContent = '';
                    sendButton.disabled = false;
                    userInput.focus();
                }
            });
        });
    </script>
</x-app-layout>