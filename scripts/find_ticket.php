<?php
// One-off helper: php scripts/find_ticket.php <barcode>
if ($argc < 2) {
    echo "Usage: php scripts/find_ticket.php <barcode>\n";
    exit(1);
}
$barcode = $argv[1];
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\EventbriteTicket;

$ticket = EventbriteTicket::where('barcode_id', $barcode)
    ->orWhere('eventbrite_ticket_id', $barcode)
    ->first();

if ($ticket) {
    echo json_encode($ticket->toArray(), JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

// Try some common normalizations
$normalized = preg_replace('/[^A-Za-z0-9]+/', '', $barcode);
if ($normalized !== $barcode) {
    $ticket = EventbriteTicket::where('barcode_id', $normalized)
        ->orWhere('eventbrite_ticket_id', $normalized)
        ->first();
    if ($ticket) {
        echo "MATCH (normalized):\n" . json_encode($ticket->toArray(), JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }
}

if (preg_match('/(\d{6,})/', $barcode, $m)) {
    $numeric = $m[1];
    $ticket = EventbriteTicket::where('barcode_id', $numeric)
        ->orWhere('eventbrite_ticket_id', $numeric)
        ->first();
    if ($ticket) {
        echo "MATCH (numeric):\n" . json_encode($ticket->toArray(), JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }
}

// If still not found, print some sample barcodes from DB to compare
$sample = EventbriteTicket::select('id','barcode_id','eventbrite_ticket_id','email')->limit(20)->get()->toArray();
echo "NOT FOUND. Sample rows:\n" . json_encode($sample, JSON_PRETTY_PRINT) . "\n";
exit(2);
