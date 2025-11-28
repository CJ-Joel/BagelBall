<?php
namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\EventbriteTicket;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;

class CheckoutController extends Controller
{
    /**
     * Show checkout success page
     * Verify payment and assign ticket to pregame
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return redirect()->route('pregames.index')
                ->with('error', 'Invalid checkout session.');
        }

        // Verify the payment was successful
        Stripe::setApiKey(config('services.stripe.secret'));
        
        try {
            $stripeSession = StripeSession::retrieve($sessionId);
        } catch (\Exception $e) {
            \Log::error('Stripe session retrieval failed', ['error' => $e->getMessage()]);
            return redirect()->route('pregames.index')
                ->with('error', 'Payment verification failed. Please contact support.');
        }

        // Check if payment was successful
        if ($stripeSession->payment_status !== 'paid') {
            return redirect()->route('pregames.index')
                ->with('error', 'Payment was not completed. Please try again.');
        }

        // Get registration from metadata
        $registrationId = $stripeSession->metadata->registration_id ?? null;
        
        if (!$registrationId) {
            return redirect()->route('pregames.index')
                ->with('error', 'Registration not found.');
        }

        // Find registration
        $registration = Registration::find($registrationId);

        if (!$registration) {
            return redirect()->route('pregames.index')
                ->with('error', 'Registration not found.');
        }

        // Assign ticket to pregame (now that payment is confirmed)
        $ticket = EventbriteTicket::find($registration->eventbrite_ticket_id);
        
        if ($ticket && !$ticket->pregame_id) {
            $ticket->update(['pregame_id' => $registration->pregame_id]);
        }

        // Mark registration as paid
        $registration->update(['payment_status' => 'paid']);

        return view('checkout.success', compact('registration'));
    }
}
