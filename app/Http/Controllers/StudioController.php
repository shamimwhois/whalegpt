<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * The split-pane studio: a preview surface on one side and the tools,
 * controls and explanation on the other.
 */
class StudioController extends Controller
{
    public function __invoke(): View
    {
        return view('chat.studio');
    }
}
