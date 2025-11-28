@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 text-white flex items-center justify-center px-4 py-6 sm:py-8">
    <div class="bg-gray-800 p-6 sm:p-8 rounded shadow text-center w-full max-w-md">
        <h1 class="text-xl sm:text-2xl font-bold mb-4">Success!</h1>
        <p class="mb-6 text-sm sm:text-base text-gray-300">Your registration and payment were successful. See you at the pre-game!</p>
        
        @if($registration->pregame->partiful_url)
            <div class="space-y-3 mb-6">
                <a href="{{ $registration->pregame->partiful_url }}" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   class="block px-4 py-3 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-500 hover:to-purple-600 rounded text-white font-semibold text-base text-center transition">
                    RSVP on Partiful
                    <span class="ml-2">→</span>
                </a>
            </div>
        @endif
        
        <a href="{{ route('pregames.index') }}" class="block sm:inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded text-white font-semibold text-center text-base">Back to Pre-Games</a>
    </div>
</div>
@endsection
