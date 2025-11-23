@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 text-white flex items-center justify-center">
    <div class="bg-gray-800 p-8 rounded shadow text-center">
        <h1 class="text-2xl font-bold mb-4">Success!</h1>
        <p class="mb-4">Your registration and payment were successful. See you at the pre-game!</p>
        <a href="{{ route('pregames.index') }}" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded text-white font-semibold">Back to Pre-Games</a>
    </div>
</div>
@endsection
