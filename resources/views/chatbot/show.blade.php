<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    @section('content')
    <div class="container text-white w-full m-10 bg-gray-800 p-4 mb-4">
        <div class="row justify-content-center">
            <div class="card-header">
                <a href="{{ route('chatbot.index') }}" class="btn btn-sm btn-outline-secondary me-2">← Back</a>
                <span>{{ $conversation->title }}</span>
            </div>
            <div class="col-md-8">

                <div class="card">
                    {{-- The Chat Window --}}
                    <div class="card-body" id="chat-box" style="height: 400px; overflow-y: scroll;">
                        @foreach($messages as $message)
                            <div
                                class="d-flex mb-3 w-full {{ $message->sender === 'user' ? 'justify-content-end' : 'justify-content-start' }}">
                                <div class="message p-2 rounded {{ $message->sender === 'user' ? 'bg-light text-black' : 'bg-primary-subtle' }}"
                                    style="max-width: 70%;">

                                    {{-- Check if the message content is an image URL --}}
                                    @if(Illuminate\Support\Str::startsWith($message->content, 'image::'))
                                        @php $url = Illuminate\Support\Str::after($message->content, 'image::'); @endphp
                                        <img src="{{ $url }}" alt="Generated Chart" class="img-fluid rounded">
                                    @else
                                        {!! nl2br(e($message->content)) !!}
                                    @endif

                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- The Input Form --}}
                    <div class="card-footer ">
                        <form id="chat-form" action="{{ route('chatbot.ask', $conversation) }}" method="POST"
                            autocomplete="off">
                            @csrf
                            <div class="input-group rounded-md text-black flex flex-row gap-3">
                                {{-- The CSRF token is already included in the form --}}
                                {{-- The input field for user messages --}}
                                <input type="text" id="user-input" class="form-control w-full rounded-md"
                                    placeholder="Type your message here..." required>
                                <button class="btn btn-primary text-white" type="submit"
                                    id="send-button">@svg('bx-send')
                                    Send
                                </button>
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

            function addMessage(content, sender, isHtml = false) {
                const messageWrapper = document.createElement('div');
                messageWrapper.className = `d-flex mb-3 w-full ${sender === 'user' ? 'justify-content-end' : 'justify-content-start'}`;

                const messageDiv = document.createElement('div');
                messageDiv.className = `message p-2 rounded ${sender === 'user' ? 'bg-light text-black' : 'bg-primary-subtle'}`;
                messageDiv.style.maxWidth = '70%';

                if (isHtml) {
                    messageDiv.innerHTML = content; // Use innerHTML for images
                } else {
                    messageDiv.innerText = content; // Use innerText for security on text content
                }

                messageWrapper.appendChild(messageDiv);
                chatBox.appendChild(messageWrapper);
                chatBox.scrollTop = chatBox.scrollHeight;
            }


            // ** UPDATED submit event listener **
            chatForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const message = userInput.value.trim();
                if (!message) return;

                // We now use the more flexible addMessage function
                addMessage(message, 'user');
                userInput.value = '';
                inputHint.textContent = 'Dini is thinking...';
                sendButton.disabled = true;

                try {
                    const response = await fetch(chatForm.action, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                        },
                        body: JSON.stringify({ message: message })
                    });

                    const data = await response.json();

                    // ** NEW LOGIC TO HANDLE COMPLEX RESPONSES **
                    if (data.message) {
                        // It's a simple text-only response
                        addMessage(data.message, 'bot');
                    } else if (data.text && data.imageUrl) {
                        // It's a mixed response with text and an image
                        addMessage(data.text, 'bot'); // Add the text part
                        // Add the image part
                        const imageHtml = `<img src="${data.imageUrl}" alt="Generated Chart" class="img-fluid rounded">`;
                        addMessage(imageHtml, 'bot', true); // `true` indicates this is HTML
                    } else {
                        addMessage('Sorry, there was an error processing your request.', 'bot');
                    }

                } catch (error) {
                    console.error('Error:', error); // Use console.error for better visibility
                    addMessage('Could not connect to the server. Please check your connection.', 'bot');
                } finally {
                    inputHint.textContent = '';
                    sendButton.disabled = false;
                    userInput.focus();
                }
            });
        });
    </script>
</x-app-layout>