<?php
header('Set-Cookie: test_cookie=hello; Path=/; SameSite=Lax');
echo "Cookie set! Check your browser's developer tools.";
?>
