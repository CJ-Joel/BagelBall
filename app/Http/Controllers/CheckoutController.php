<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    // Show checkout success page
    public function success()
    {
        return view('checkout.success');
    }
}
