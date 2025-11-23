@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 text-white py-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Bagel Ball Pre-Games</h1>
        <ul>
            @foreach($pregames as $pregame)
                <li class="mb-6 p-6 bg-gray-800 rounded shadow">
                    <div class="mb-2">
                        <span class="text-xl font-semibold">{{ $pregame->name }}</span>
                        <span class="ml-2 px-2 py-1 rounded bg-gray-700 text-xs">{{ $pregame->label() }}</span>
                    </div>
                    <div class="mb-2 text-gray-400 text-sm">
                        {{ \Illuminate\Support\Carbon::parse($pregame->start_time)->format('M d, Y H:i') }} @ {{ $pregame->location }}
                    </div>
                    <div class="mb-2 text-gray-300">{{ $pregame->description }}</div>
                    <div class="mb-2 text-xs">Spots left: {{ $pregame->spotsRemaining() }}</div>
                    <a href="{{ route('pregames.signup', $pregame) }}" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded text-white font-semibold mt-2">Sign Up</a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
