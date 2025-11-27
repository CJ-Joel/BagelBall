<?php
$response = new \Symfony\Component\HttpFoundation\Response('Test', 200, ['Content-Type' => 'text/plain']);
$response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie(
    'test_cookie',
    'value',
    0,
    '/',
    null,
    false,
    false,
    false,
    'Lax'
));

// Send the response
$response->send();
