@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 text-white py-8">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-2xl font-bold mb-4">Eventbrite Webhook Log</h1>
        <pre class="bg-gray-800 p-4 rounded overflow-x-auto text-sm">{{ $log }}</pre>
    </div>
</div>
@endsection
