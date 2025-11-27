<?php
// Absolutely minimal cookie test - output nothing
header('Set-Cookie: minimal_test=12345; Path=/; HttpOnly; SameSite=Lax');
header('Content-Type: application/json');
echo json_encode(['test' => 'minimal']);
?>
