<?php
require __DIR__ . '/../vendor/autoload.php';

$response = response('Test response', 200, ['Content-Type' => 'text/plain']);
$response->cookie('test_cookie_via_response', 'value123', 0, '/');
$response->send();
