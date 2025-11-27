<?php
// Minimal cookie test
header('Set-Cookie: test_cookie=test_value123; Path=/; HttpOnly; SameSite=Lax');
echo "If you see this, the script ran.";
?>
