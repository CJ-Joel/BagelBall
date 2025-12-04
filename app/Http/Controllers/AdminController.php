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

        return view('admin.tickets-sold-by-day', [
            'labels' => $labels,
            'currentCounts' => $alignedCurrent,
            'historicalCounts' => $alignedHistorical,
            'totalSignedUp' => $totalSignedUp,
            'pregameBreakdown' => $pregameBreakdown,
        ]);
    }
}
