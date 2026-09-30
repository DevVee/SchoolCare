<?php

namespace App\Http\Controllers;

use App\Services\LandingContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The public website: the landing page at "/" (guests only; signed-in staff go
 * straight to their dashboard) and the privacy notice. Content comes from
 * Administration → Website (App\Services\LandingContent).
 */
class LandingController extends Controller
{
    public function show(LandingContent $content): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('landing.index', ['page' => $content->page()]);
    }

    public function privacy(LandingContent $content): View
    {
        return view('landing.privacy', ['page' => $content->page()]);
    }
}
