<?php
namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     * 
     * NOTE: Be specific with paths to avoid disrupting middleware chains.
     * Avoid broad wildcards like 'pregames/*' - instead list specific routes.
     *
     * @var array<int, string>
     */
    protected $except = [
        'webhooks/*',
        'eventbrite/sync/run',
        'pregames/validate-order',
    ];
}
