<?php
// Bypass Laravel - use pure PHP sessions
session_start();

if (!isset($_SESSION['visit_count'])) {
    $_SESSION['visit_count'] = 0;
}

$_SESSION['visit_count']++;

echo "Visit Count: " . $_SESSION['visit_count'] . "\n";
echo "Session ID: " . session_id() . "\n";
?>
