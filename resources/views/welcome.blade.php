<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bagel Ball Pre-Games</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center">
    <div class="max-w-lg w-full p-8 bg-gray-800 rounded shadow text-center">
        <h1 class="text-4xl font-bold mb-4">Bagel Ball Pre-Games</h1>
        <p class="mb-6 text-gray-300">Join the party before the game! Find a pre-game, sign up, and get ready for fun. No account needed.</p>
        <a href="{{ route('pregames.index') }}" class="inline-block px-6 py-3 bg-blue-600 hover:bg-blue-700 rounded text-white font-semibold text-lg">View Pre-Games</a>
    </div>
</body>
</html>
