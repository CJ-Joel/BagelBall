<?php

namespace App\Http\Controllers;

use App\Models\EventbriteTicket;
use Illuminate\Http\Request;
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
        $barcode = trim((string) $request->input('barcode'));
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
            return response()->json([
                'ok' => false,
                'status' => 'not_found',
                'barcode' => $barcode,
            ]);
        }

        $name = trim((string) ($ticket->first_name . ' ' . $ticket->last_name));
        $name = $name !== '' ? $name : ($ticket->email ?? 'Unknown');

        $alreadyRedeemed = $ticket->isRedeemed();
        if (!$alreadyRedeemed) {
            $ticket->redeemed_at = now();
            $ticket->save();
        }
            return response()->json([
            'ok' => true,
            'status' => $alreadyRedeemed ? 'already_redeemed' : 'redeemed',
            'name' => $name,
            'email' => $ticket->email,
            'ticket_id' => $ticket->eventbrite_ticket_id,
            'barcode_id' => $ticket->barcode_id,
            'redeemed_at' => optional($ticket->redeemed_at)->toIso8601String(),
            ]);
    }
}
