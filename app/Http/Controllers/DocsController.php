<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class DocsController extends Controller
{
    /**
     * The documentation index: an overview of the guides on offer.
     */
    public function index(): View
    {
        return view('pages.docs.index', [
            'pages' => config('docs.pages', []),
        ]);
    }

    /**
     * One documentation page, or a 404 when the slug is not published.
     */
    public function show(string $page): View
    {
        $pages = config('docs.pages', []);

        abort_unless(is_array($pages) && array_key_exists($page, $pages), 404);

        return view('pages.docs.guide', [
            'slug' => $page,
            'page' => $pages[$page],
            'pages' => $pages,
        ]);
    }
}
