@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 text-white py-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold mb-4">{{ $pregame->name }}</h1>
        <div class="mb-2 text-gray-400">{{ \Illuminate\Support\Carbon::parse($pregame->start_time)->format('M d, Y H:i') }} @ {{ $pregame->location }}</div>
        <div class="mb-4">{{ $pregame->description }}</div>
        <div class="mb-4">
            <span class="px-2 py-1 rounded bg-gray-700 text-xs">{{ $pregame->label() }}</span>
            <span class="ml-2 text-xs">Spots left: {{ $pregame->spotsRemaining() }}</span>
        </div>
        <a href="{{ route('pregames.signup', $pregame) }}" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded text-white font-semibold">Sign Up</a>
    </div>
</div>
@endsection
