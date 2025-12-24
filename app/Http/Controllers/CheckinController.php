<?php

namespace App\Http\Controllers;

use App\Models\EventbriteTicket;
use App\Models\CheckinAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Registration;

class CheckinController extends Controller
{
    public function showLogin(Request $request)
    {
        return view('checkin.login');
    }

    public function index(Request $request)
    {
        return view('checkin.index');
    }

    public function scan(Request $request)
    {
        // Respect server-side read-only mode if enabled via env CHECKIN_READONLY
        if (env('CHECKIN_READONLY', false)) {
            try {
                CheckinAudit::create([
                    'eventbrite_ticket_id' => null,
                    'barcode_id' => trim((string) $request->input('barcode')) ?: null,
                    'action' => 'scan_attempt',
                    'status' => 'read_only',
                    'actor' => $request->header('X-CHECKIN-TOKEN') ?? null,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr($request->userAgent() ?? '', 0, 1000),
                ]);
            } catch (\Throwable $e) {
                Log::debug('audit write failed', ['err' => $e->getMessage()]);
            }

            return response()->json(['ok' => false, 'status' => 'read_only', 'message' => 'Check-in is read-only'], 423);
        }
        $barcode = trim((string) $request->input('barcode'));
        $proceedWaitlist = $request->boolean('proceed_waitlist', false);
        if (config('app.debug')) {
            Log::debug('checkin.scan received', ['barcode' => $barcode, 'ip' => $request->ip()]);
        }
        if ($barcode === '') {
            return response()->json(['error' => 'missing_barcode'], 422);
        }

        // Eventbrite attendee barcode is typically in `barcode_id`.
        // Also accept a full ticket id if someone scans that.
        $ticket = EventbriteTicket::query()
            ->where('barcode_id', $barcode)
            ->orWhere('eventbrite_ticket_id', $barcode)
            ->first();

        if (!$ticket) {
            $resp = [
                'ok' => false,
                'status' => 'not_found',
                'barcode' => $barcode,
            ];
            if (config('app.debug')) {
                // Echo back the received raw barcode for debugging
                $resp['debug_received'] = $barcode;
            }
            // Audit not-found lookup
            try {
                CheckinAudit::create([
                    'eventbrite_ticket_id' => null,
                    'barcode_id' => $barcode,
                    'action' => 'scan',
                    'status' => 'not_found',
                    'actor' => $request->header('X-CHECKIN-TOKEN') ?? null,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr($request->userAgent() ?? '', 0, 1000),
                ]);
            } catch (\Throwable $e) {
                Log::debug('audit write failed', ['err' => $e->getMessage()]);
            }
            return response()->json($resp);
        }

        $name = trim((string) ($ticket->first_name . ' ' . $ticket->last_name));
        $name = $name !== '' ? $name : ($ticket->email ?? 'Unknown');

        $isWaitlist = is_string($ticket->ticket_type) && stripos($ticket->ticket_type, 'waitlist') !== false;

        // A registration record linked to this ticket means the guest gets a drink.
        // Some code paths store the local tickets.id in registrations.eventbrite_ticket_id,
        // others may store the external Eventbrite ticket id. Check both to be safe.
        $drinkEligible = Registration::query()
            ->where(function ($q) use ($ticket) {
                $q->where('eventbrite_ticket_id', $ticket->id)
                  ->orWhere('eventbrite_ticket_id', $ticket->eventbrite_ticket_id);
            })
            ->exists();

        // Require explicit confirmation before checking in waitlist tickets (only if not already redeemed)
        if ($isWaitlist && !$proceedWaitlist && !$ticket->isRedeemed()) {
            return response()->json([
                'ok' => true,
            'status' => 'waitlist_confirm',
                'name' => $name,
                'email' => $ticket->email,
                'ticket_id' => $ticket->eventbrite_ticket_id,
                'barcode_id' => $ticket->barcode_id,
                'ticket_type' => $ticket->ticket_type,
                'drink_eligible' => $drinkEligible,
            ]);
        }

        $alreadyRedeemed = $ticket->isRedeemed();
        if (!$alreadyRedeemed) {
            $ticket->redeemed_at = now();
            $ticket->save();
        }
        // Record audit for scan
        try {
            CheckinAudit::create([
                'eventbrite_ticket_id' => $ticket->id ?? null,
                'barcode_id' => $ticket->barcode_id,
                'action' => 'scan',
                'status' => $alreadyRedeemed ? 'already_redeemed' : 'redeemed',
                'actor' => $request->header('X-CHECKIN-TOKEN') ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 1000),
            ]);
        } catch (\Throwable $e) {
            Log::debug('audit write failed', ['err' => $e->getMessage()]);
        }

        return response()->json([
            'ok' => true,
            'status' => $alreadyRedeemed ? 'already_redeemed' : 'redeemed',
            'name' => $name,
            'email' => $ticket->email,
            'ticket_id' => $ticket->eventbrite_ticket_id,
            'barcode_id' => $ticket->barcode_id,
            'redeemed_at' => optional($ticket->redeemed_at)->toIso8601String(),
            'drink_eligible' => $drinkEligible,
                'ticket_type' => $ticket->ticket_type,
            ]);
    }

    /**
     * Reverse a checkin (clear redeemed_at) for a ticket identified by barcode or ticket id.
     */
    public function reverse(Request $request)
    {
        // Respect server-side read-only mode if enabled via env CHECKIN_READONLY
        if (env('CHECKIN_READONLY', false)) {
            try {
                CheckinAudit::create([
                    'eventbrite_ticket_id' => null,
                    'barcode_id' => trim((string) $request->input('barcode')) ?: null,
                    'action' => 'reverse_attempt',
                    'status' => 'read_only',
                    'actor' => $request->header('X-CHECKIN-TOKEN') ?? null,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr($request->userAgent() ?? '', 0, 1000),
                ]);
            } catch (\Throwable $e) {
                Log::debug('audit write failed', ['err' => $e->getMessage()]);
            }

            return response()->json(['ok' => false, 'status' => 'read_only', 'message' => 'Check-in is read-only'], 423);
        }
        $barcode = trim((string) $request->input('barcode'));
        if (config('app.debug')) {
            Log::debug('checkin.reverse received', ['barcode' => $barcode, 'ip' => $request->ip()]);
        }

        if ($barcode === '') {
            return response()->json(['error' => 'missing_barcode'], 422);
        }

        $ticket = EventbriteTicket::query()
            ->where('barcode_id', $barcode)
            ->orWhere('eventbrite_ticket_id', $barcode)
            ->first();

        if (! $ticket) {
            return response()->json([
                'ok' => false,
                'status' => 'not_found',
                'barcode' => $barcode,
            ]);
        }

        if (! $ticket->isRedeemed()) {
            return response()->json([
                'ok' => false,
                'status' => 'not_redeemed',
                'ticket_id' => $ticket->eventbrite_ticket_id,
                'barcode_id' => $ticket->barcode_id,
            ]);
        }

        // Clear redeemed_at (reverse checkin)
        $ticket->redeemed_at = null;
        $ticket->save();

        // Record audit for reverse
        try {
            CheckinAudit::create([
                'eventbrite_ticket_id' => $ticket->id ?? null,
                'barcode_id' => $ticket->barcode_id,
                'action' => 'reverse',
                'status' => 'reversed',
                'actor' => $request->header('X-CHECKIN-TOKEN') ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 1000),
            ]);
        } catch (\Throwable $e) {
            Log::debug('audit write failed', ['err' => $e->getMessage()]);
        }

        return response()->json([
            'ok' => true,
            'status' => 'reversed',
            'ticket_id' => $ticket->eventbrite_ticket_id,
            'barcode_id' => $ticket->barcode_id,
            'name' => trim((string) ($ticket->first_name . ' ' . $ticket->last_name)),
            'email' => $ticket->email,
        ]);
    }
}
