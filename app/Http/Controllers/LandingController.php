<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class LandingController extends Controller
{
    /**
     * The public landing page.
     *
     * A signed-in visitor is offered a link into the app; a guest is offered
     * the authentication modal instead, so the same page serves both without
     * a redirect that would hide the product from anyone evaluating it.
     */
    public function __invoke(): View
    {
        return view('pages.landing');
    }
}
