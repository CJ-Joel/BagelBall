@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 text-white py-6 sm:py-8 px-4 sm:px-0">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl sm:text-3xl font-bold mb-6">Bagel Ball Pre-Games</h1>
        
        <!-- Hosting Opportunity Banner removed per request -->
        
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
                    @if($pregame->isFull())
                        <span class="block sm:inline-block px-4 py-2 bg-gray-600 rounded text-gray-400 font-semibold text-center text-base cursor-not-allowed">Full - No Spots Available</span>
                    @else
                        <a href="{{ route('pregames.signup', $pregame) }}" class="block sm:inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded text-white font-semibold text-center text-base">Sign Up</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
