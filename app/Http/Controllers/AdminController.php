<?php

namespace App\Http\Controllers;

use App\Models\EventbriteTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Registration;
use App\Models\PreGame;

class AdminController extends Controller
{
    public function ticketsSoldByDay()
    {
        // Allow access only with a valid admin token in the querystring.
        $rawToken = request()->query('token');
        if (! $rawToken) {
            return redirect()->route('admin.login');
        }

        // validate token against admin_tokens (hashed)
        $tokenRecord = \App\Models\AdminToken::where('expires_at', '>=', now())->get();
        $valid = false;
        foreach ($tokenRecord as $r) {
            if (\Illuminate\Support\Facades\Hash::check($rawToken, $r->token_hash)) {
                $valid = true;
                break;
            }
        }
        if (! $valid) {
            return redirect()->route('admin.login');
        }

        // Current year cumulative from EventbriteTicket
        // Adjust order_date by subtracting 5 hours to account for timezone differences
        $currentTickets = EventbriteTicket::select(
            DB::raw('DATE(DATE_SUB(order_date, INTERVAL 5 HOUR)) as date'),
            DB::raw('COUNT(*) as count')
        )
        ->whereNotNull('order_date')
        ->whereRaw('YEAR(DATE_SUB(order_date, INTERVAL 5 HOUR)) = ?', [now()->year])
        ->groupBy(DB::raw('DATE(DATE_SUB(order_date, INTERVAL 5 HOUR))'))
        ->orderBy('date', 'asc')
        ->get();

        $currentCumulative = 0;
        $currentCounts = $currentTickets->map(function($row) use (&$currentCumulative) {
            $currentCumulative += $row->count;
            return $currentCumulative;
        })->toArray();

        $currentDates = $currentTickets->pluck('date')->map(fn($d) => date('M d, Y', strtotime($d)))->toArray();

        // 2024 cumulative from daily_ticket_sales table if present
        $historical = DB::table('daily_ticket_sales')
            ->select('date', 'cumulative_count')
            ->whereYear('date', 2024)
            ->orderBy('date', 'asc')
            ->get();

        $historicalDates = $historical->pluck('date')->map(fn($d) => date('M d, Y', strtotime($d)))->toArray();
        $historicalCounts = $historical->pluck('cumulative_count')->toArray();

        // Build labels as month-day (omit year), and align values by month-day
        $monthDaySet = collect([]);
        foreach ($currentTickets as $row) {
            $monthDaySet->push(date('M d', strtotime($row->date)));
        }
        foreach ($historical as $row) {
            $monthDaySet->push(date('M d', strtotime($row->date)));
        }
        $labels = $monthDaySet->unique()->sort(function($a, $b) {
            return strtotime($a) <=> strtotime($b);
        })->values()->toArray();

        // Maps keyed by month-day
        $currentMap = [];
        $running = 0;
        foreach ($currentTickets as $row) {
            $running += $row->count;
            $key = date('M d', strtotime($row->date));
            $currentMap[$key] = $running;
        }
        $historicalMap = [];
        foreach ($historical as $row) {
            $key = date('M d', strtotime($row->date));
            $historicalMap[$key] = $row->cumulative_count;
        }

        // Align series to month-day labels (carry forward last known value)
        $alignedCurrent = [];
        $alignedHistorical = [];
        $lastCurrent = 0;
        $lastHistorical = 0;
        foreach ($labels as $key) {
            if (array_key_exists($key, $currentMap)) { $lastCurrent = $currentMap[$key]; }
            if (array_key_exists($key, $historicalMap)) { $lastHistorical = $historicalMap[$key]; }
            $alignedCurrent[] = $lastCurrent;
            $alignedHistorical[] = $lastHistorical;
        }

        // Show current year only up to today's date
        $todayKey = date('M d');
        $seenToday = false;
        foreach ($labels as $i => $key) {
            if ($key === $todayKey) { $seenToday = true; }
            if ($seenToday && $key !== $todayKey) {
                $alignedCurrent[$i] = null; // null values are skipped by Chart.js
            }
        }

        // Widget: number of people signed up for pregames (paid)
        // Count email + friend_email where payment_status = 'paid'
        $paidRegistrations = Registration::query()
            ->where('payment_status', 'paid')
            ->select([
                DB::raw('SUM(CASE WHEN email IS NOT NULL AND email <> "" THEN 1 ELSE 0 END) as email_count'),
                DB::raw('SUM(CASE WHEN friend_email IS NOT NULL AND friend_email <> "" THEN 1 ELSE 0 END) as friend_email_count')
            ])
            ->first();

        $totalSignedUp = ($paidRegistrations->email_count ?? 0) + ($paidRegistrations->friend_email_count ?? 0);

        // Breakdown per pregame (paid), counting email + friend_email
        $perPregame = Registration::query()
            ->where('payment_status', 'paid')
            ->select([
                'pregame_id',
                DB::raw('SUM(CASE WHEN email IS NOT NULL AND email <> "" THEN 1 ELSE 0 END) as email_count'),
                DB::raw('SUM(CASE WHEN friend_email IS NOT NULL AND friend_email <> "" THEN 1 ELSE 0 END) as friend_email_count')
            ])
            ->groupBy('pregame_id')
            ->get()
            ->map(function($row) {
                return [
                    'pregame_id' => $row->pregame_id,
                    'total' => (int)($row->email_count + $row->friend_email_count),
                ];
            })
            ->keyBy('pregame_id');

        // Fetch pregame names
        $pregameNames = PreGame::query()
            ->whereIn('id', $perPregame->keys()->all())
            ->pluck('name', 'id');

        $pregameBreakdown = [];
        foreach ($perPregame as $pid => $data) {
            $pregameBreakdown[] = [
                'name' => $pregameNames[$pid] ?? ('Pregame #' . $pid),
                'total' => $data['total'],
            ];
        }

        // Gender breakdown from EventbriteTicket (normalize common values)
        $genderRow = EventbriteTicket::selectRaw(
            "SUM(CASE WHEN LOWER(TRIM(gender)) IN ('male','m') THEN 1 ELSE 0 END) as male_count, " .
            "SUM(CASE WHEN LOWER(TRIM(gender)) IN ('female','f') THEN 1 ELSE 0 END) as female_count, " .
            "SUM(CASE WHEN gender IS NULL OR TRIM(gender) = '' THEN 1 ELSE 0 END) as unknown_count"
        )->first();

        $genderCounts = [
            'male' => (int)($genderRow->male_count ?? 0),
            'female' => (int)($genderRow->female_count ?? 0),
            'unknown' => (int)($genderRow->unknown_count ?? 0),
        ];

        // ========== Night of Operations Data ==========
        // Ticket counts
        $ticketsSold = EventbriteTicket::count();
        $ticketsAdmitted = EventbriteTicket::whereNotNull('redeemed_at')->count();
        $ticketsOutstanding = $ticketsSold - $ticketsAdmitted;

        // Scans before/after 10:05 PM (using redeemed_at time portion)
        // We check if the TIME part of redeemed_at is before or after 22:05:00
        $scansBefore1005 = EventbriteTicket::whereNotNull('redeemed_at')
            ->whereRaw("TIME(redeemed_at) < '22:05:00'")
            ->count();
        $scansAfter1005 = EventbriteTicket::whereNotNull('redeemed_at')
            ->whereRaw("TIME(redeemed_at) >= '22:05:00'")
            ->count();

        // Bar chart: scans by 15-min increment over a 5-hour window (e.g., 7 PM to midnight = 20 intervals)
        // We'll bucket by the TIME portion of redeemed_at into 15-min slots
        $scansByInterval = EventbriteTicket::selectRaw(
            "CONCAT(
                LPAD(HOUR(redeemed_at), 2, '0'), ':',
                LPAD(FLOOR(MINUTE(redeemed_at) / 15) * 15, 2, '0')
            ) as time_slot,
            COUNT(*) as count"
        )
        ->whereNotNull('redeemed_at')
        ->groupBy('time_slot')
        ->orderBy('time_slot')
        ->get();

        // Build full set of 15-min labels from 7 PM (19:00) to midnight (00:00) = 5 hours = 20 slots
        $intervalLabels = [];
        $intervalCounts = [];
        $slotMap = $scansByInterval->pluck('count', 'time_slot')->toArray();

        for ($hour = 19; $hour <= 23; $hour++) {
            for ($min = 0; $min < 60; $min += 15) {
                $slot = sprintf('%02d:%02d', $hour, $min);
                $intervalLabels[] = $slot;
                $intervalCounts[] = $slotMap[$slot] ?? 0;
            }
        }
        // Add midnight slot
        $intervalLabels[] = '00:00';
        $intervalCounts[] = $slotMap['00:00'] ?? 0;

        // Gender breakdown of scanned (admitted) tickets only
        $scannedGenderRow = EventbriteTicket::selectRaw(
            "SUM(CASE WHEN LOWER(TRIM(gender)) IN ('male','m') THEN 1 ELSE 0 END) as male_count, " .
            "SUM(CASE WHEN LOWER(TRIM(gender)) IN ('female','f') THEN 1 ELSE 0 END) as female_count, " .
            "SUM(CASE WHEN gender IS NULL OR TRIM(gender) = '' THEN 1 ELSE 0 END) as unknown_count"
        )->whereNotNull('redeemed_at')->first();

        $scannedGenderCounts = [
            'male' => (int)($scannedGenderRow->male_count ?? 0),
            'female' => (int)($scannedGenderRow->female_count ?? 0),
            'unknown' => (int)($scannedGenderRow->unknown_count ?? 0),
        ];

        return view('admin.tickets-sold-by-day', [
            'labels' => $labels,
            'currentCounts' => $alignedCurrent,
            'historicalCounts' => $alignedHistorical,
            'totalSignedUp' => $totalSignedUp,
            'pregameBreakdown' => $pregameBreakdown,
            'genderCounts' => $genderCounts,
            // Night of Operations
            'ticketsSold' => $ticketsSold,
            'ticketsAdmitted' => $ticketsAdmitted,
            'ticketsOutstanding' => $ticketsOutstanding,
            'scansBefore1005' => $scansBefore1005,
            'scansAfter1005' => $scansAfter1005,
            'intervalLabels' => $intervalLabels,
            'intervalCounts' => $intervalCounts,
            'scannedGenderCounts' => $scannedGenderCounts,
        ]);
    }
}
