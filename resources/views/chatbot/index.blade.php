<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Hi!') . ' ' . $user->name }}, {{ __('Welcome to CekDini!') }}
        </h2>
    </x-slot>

    <div class="card mt-8 text-white bg-gray-800 p-4 mb-4">



        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Your Conversations</span>
                        <form action="{{ route('chatbot.start') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary">Start New Screening</button>
                        </form>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse ($conversations as $conversation)
                            <a href="{{ route('chatbot.show', $conversation) }}"
                                class="list-group-item list-group-item-action">
                                {{ $conversation->title }}
                            </a>
                        @empty
                            <div class="list-group-item">You have no past conversations.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>