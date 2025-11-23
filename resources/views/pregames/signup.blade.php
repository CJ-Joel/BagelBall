@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 text-white py-8">
    <div class="max-w-md mx-auto">
        <h1 class="text-2xl font-bold mb-4">Sign Up for {{ $pregame->name }}</h1>
        <form method="POST" action="{{ route('pregames.signup.submit', $pregame) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block mb-1">Your Name</label>
                <input type="text" name="name" class="w-full px-3 py-2 rounded bg-gray-800 text-white" required>
            </div>
            <div>
                <label class="block mb-1">Your Email</label>
                <input type="email" name="email" class="w-full px-3 py-2 rounded bg-gray-800 text-white" required>
            </div>
            <div>
                <label class="block mb-1">Friend's Name (optional)</label>
                <input type="text" name="friend_name" class="w-full px-3 py-2 rounded bg-gray-800 text-white">
            </div>
            <div>
                <label class="block mb-1">Friend's Email (optional)</label>
                <input type="email" name="friend_email" class="w-full px-3 py-2 rounded bg-gray-800 text-white">
            </div>
            <button type="submit" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded text-white font-semibold">Continue to Payment</button>
        </form>
    </div>
</div>
@endsection
