@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 text-white py-6 sm:py-8 px-4 sm:px-0">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl sm:text-3xl font-bold mb-6">Bagel Ball Pre-Games</h1>
        
        <!-- Hosting Opportunity Banner -->
        <div class="mb-8 p-4 sm:p-6 bg-gradient-to-r from-purple-900 to-purple-800 rounded-lg border border-purple-600">
            <h2 class="text-lg sm:text-xl font-bold mb-2">We're Looking for Hosts!</h2>
            <p class="text-sm sm:text-base text-purple-100 mb-3">
                We're looking to add more pre-games to the list. Interested in hosting a small group pre-game? We'll provide funding, a free Bagel Ball ticket, and fun people to make it happen.
            </p>
            <a href="mailto:shalom@bagelball.org?subject=Interested%20in%20Hosting%20a%20Bagel%20Ball%20Pre-Game&body=Hi%20Shalom%2C%0A%0AI%27m%20interested%20in%20hosting%20a%20pre-game%20for%20Bagel%20Ball.%0A%0AName%3A%0A%0APart%20of%20Town%3A%0A%0ANumber%20of%20People%20I%27m%20Interested%20in%20Hosting%3A%0A%0AAny%20Other%20Details%3A%0A%0AThanks%21" class="inline-block px-4 py-2 bg-purple-500 hover:bg-purple-600 rounded font-semibold text-sm sm:text-base transition">
                Reach Out to Host →
            </a>
        </div>
        
        <ul class="space-y-4 sm:space-y-6">
            @foreach($pregames as $pregame)
                <li class="p-4 sm:p-6 bg-gray-800 rounded shadow hover:bg-gray-750 transition">
                    <div class="mb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <span class="text-lg sm:text-xl font-semibold">{{ $pregame->name }}</span>
                        <span class="px-2 py-1 rounded bg-gray-700 text-xs w-fit">{{ $pregame->label() }}</span>
                    </div>
                    <div class="mb-2 text-gray-400 text-sm">
                        {{ \Illuminate\Support\Carbon::parse($pregame->start_time)->format('M d, Y g:i A') }} @ {{ $pregame->location }}
                    </div>
                    <div class="mb-3 text-gray-300 text-sm">{{ $pregame->description }}</div>
                    <div class="mb-3 text-xs text-gray-400">Spots left: {{ $pregame->spotsRemaining() }}</div>
                    <a href="{{ route('pregames.signup', $pregame) }}" class="block sm:inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded text-white font-semibold text-center text-base">Sign Up</a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
