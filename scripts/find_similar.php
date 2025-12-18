<?php
// Usage: php scripts/find_similar.php <barcode>
if ($argc < 2) {
    echo "Usage: php scripts/find_similar.php <barcode>\n";
    exit(1);
}
$barcode = $argv[1];
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EventbriteTicket;

$found = [];
$len = strlen($barcode);
// Try substrings length 8..12
for ($l = 12; $l >= 8; $l--) {
    for ($i = 0; $i + $l <= $len; $i++) {
        $sub = substr($barcode, $i, $l);
        $matches = EventbriteTicket::where('barcode_id', 'like', "%{$sub}%")
            ->orWhere('eventbrite_ticket_id', 'like', "%{$sub}%")
            ->limit(10)
            ->get();
        if ($matches->isNotEmpty()) {
            $found[$l][$sub] = $matches->map(function($m){
                return [
                    'id' => $m->id,
                    'barcode_id' => $m->barcode_id,
                    'eventbrite_ticket_id' => $m->eventbrite_ticket_id,
                    'email' => $m->email,
                ];
            })->toArray();
        }
    }
}

if (empty($found)) {
    echo "No similar barcode substrings found.\n";
    exit(2);
}

echo json_encode($found, JSON_PRETTY_PRINT) . "\n";
exit(0);
