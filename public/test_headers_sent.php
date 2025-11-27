<?php
$output = "";
$output .= "At script start - headers_sent: " . (headers_sent() ? 'YES' : 'NO') . "\n";

header('X-Test: value-via-header-function');
header('Set-Cookie: test=value; Path=/; SameSite=Lax');

$output .= "After header() - headers_sent: " . (headers_sent() ? 'YES' : 'NO') . "\n";
$output .= "Script execution complete.\n";

echo $output;
?>
