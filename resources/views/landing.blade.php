@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gradient-to-b from-slate-900 via-slate-900 to-slate-800 text-white">
    <!-- Hero Section -->
    <div class="min-h-screen flex flex-col items-center justify-center px-4 sm:px-6 py-16 sm:py-20">
        <div class="max-w-3xl text-center">
            <!-- Logo/Branding -->
            <div class="mb-6 sm:mb-8">
                <h1 class="text-4xl sm:text-5xl lg:text-7xl font-bold mb-2 sm:mb-4 tracking-tight">
                    Bagel Ball
                    <span class="block text-transparent bg-clip-text bg-gradient-to-r from-yellow-300 to-yellow-200">Pre-Games</span>
                </h1>
            </div>

            <!-- Subheading -->
            <p class="text-base sm:text-xl lg:text-2xl text-slate-300 mb-8 leading-relaxed max-w-2xl mx-auto">
                Join intimate, curated gatherings across DMV before the main event. Meet new people, make connections, and get to know your Bagel Ball community.
            </p>

            <!-- Key Features -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 mb-8 sm:mb-12 mt-8 sm:mt-12">
                <div class="bg-white/5 backdrop-blur-sm border border-yellow-300/20 rounded-lg p-4 sm:p-6 hover:border-yellow-300/40 transition">
                    <div class="text-yellow-300 text-2xl sm:text-3xl mb-3">🤝</div>
                    <h3 class="text-base sm:text-lg font-semibold mb-2">Meet People</h3>
                    <p class="text-slate-400 text-xs sm:text-sm">Connect with fellow Bagel Ball attendees before the main event.</p>
                </div>
                <div class="bg-white/5 backdrop-blur-sm border border-yellow-300/20 rounded-lg p-4 sm:p-6 hover:border-yellow-300/40 transition">
                    <div class="text-yellow-300 text-2xl sm:text-3xl mb-3">⚡</div>
                    <h3 class="text-base sm:text-lg font-semibold mb-2">Accelerated Entry</h3>
                    <p class="text-slate-400 text-xs sm:text-sm">Skip the line at Bagel Ball if you attend a pre-game.</p>
                </div>
                <div class="bg-white/5 backdrop-blur-sm border border-yellow-300/20 rounded-lg p-4 sm:p-6 hover:border-yellow-300/40 transition">
                    <div class="text-yellow-300 text-2xl sm:text-3xl mb-3">🌟</div>
                    <h3 class="text-base sm:text-lg font-semibold mb-2">Limited Spaces</h3>
                    <p class="text-slate-400 text-xs sm:text-sm">Small, intimate groups hosted by community members.</p>
                </div>
            </div>

            <!-- Important Notice -->
            <div class="bg-yellow-300/10 border-l-4 border-yellow-300 rounded px-4 sm:px-6 py-3 sm:py-4 mb-8 sm:mb-12 text-left max-w-2xl mx-auto">
                <p class="text-yellow-200 font-semibold mb-2 text-sm sm:text-base">📌 Important</p>
                <p class="text-slate-200 text-xs sm:text-sm">
                    You must already have a valid <strong>Bagel Ball ticket</strong> to join a pre-game. Pre-games are exclusive to ticket holders only.
                </p>
            </div>

            <!-- CTA Buttons -->
            <div class="flex flex-col gap-3 sm:gap-4 justify-center max-w-2xl mx-auto">
                <!-- Primary Button: Explore Pre-Games -->
                <a href="{{ route('pregames.index') }}"
                   class="flex-1 px-6 sm:px-8 py-3 sm:py-4 bg-gradient-to-r from-yellow-400 to-yellow-300 text-slate-950 font-bold text-base sm:text-lg rounded-lg hover:from-yellow-300 hover:to-yellow-200 transition transform hover:scale-105 duration-200 shadow-lg hover:shadow-yellow-400/50 text-center">
                    Explore Pre-Games
                    <span class="ml-2">→</span>
                </a>

                <!-- Secondary Button: Get Tickets -->
                <a href="https://bagelball.eventbrite.com/"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="flex-1 px-6 sm:px-8 py-3 sm:py-4 border-2 border-yellow-300 text-yellow-300 font-bold text-base sm:text-lg rounded-lg hover:bg-yellow-300/10 transition duration-200 text-center">
                    Get Bagel Ball Tickets
                    <span class="ml-2">🎫</span>
                </a>
            </div>

            <!-- Secondary Text -->
            <p class="text-slate-400 text-xs sm:text-sm mt-6 sm:mt-8">
                Don't have Bagel Ball tickets yet? Purchase them on Eventbrite first.
            </p>
        </div>
    </div>

    <!-- Footer Section (Optional) -->
    <div class="bg-slate-950 border-t border-slate-700 py-6 sm:py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 text-center">
            <p class="text-slate-400 text-xs sm:text-sm">
                Bagel Ball Pre-Games • Community-hosted gatherings • Limited spaces available
            </p>
        </div>
    </div>
</div>
@endsection
