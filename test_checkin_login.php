<?php
require 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$password = 'BagelBall2025!';
$envPlain = env('ADMIN_PASSWORD');

echo "Env password: " . var_export($envPlain, true) . "\n";
echo "Input password: " . var_export($password, true) . "\n";
echo "Match: " . (hash_equals((string)$envPlain, $password) ? 'YES' : 'NO') . "\n";
echo "Lengths: env=" . strlen($envPlain) . " input=" . strlen($password) . "\n";
echo "Chars match: ";
for ($i = 0; $i < max(strlen($envPlain), strlen($password)); $i++) {
    $e = $envPlain[$i] ?? '?';
    $p = $password[$i] ?? '?';
    echo ($e === $p ? '✓' : "✗($e!=$p)");
}
echo "\n";
