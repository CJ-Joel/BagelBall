@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 text-white py-6 sm:py-8 px-4 sm:px-0">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl sm:text-3xl font-bold mb-4">{{ $pregame->name }}</h1>
        <div class="mb-3 text-gray-400 text-sm sm:text-base">{{ \Illuminate\Support\Carbon::parse($pregame->start_time)->format('M d, Y H:i') }} @ {{ $pregame->location }}</div>
        <div class="mb-4 text-sm sm:text-base">{{ $pregame->description }}</div>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 text-sm">
            <span class="px-2 py-1 rounded bg-gray-700 text-xs w-fit">{{ $pregame->label() }}</span>
            <span class="text-xs text-gray-400">Spots left: {{ $pregame->spotsRemaining() }}</span>
        </div>
        <a href="{{ route('pregames.signup', $pregame) }}" class="block sm:inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded text-white font-semibold text-center text-base">Sign Up</a>
    </div>
</div>
@endsection
