<?php

namespace App\Http\Controllers;

use App\Services\LandingContent;
use App\Support\ProductSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The public website:
 *   "/"         the product page (what the app is; vendor copy in App\Support\ProductSite).
 *               Guests only: signed-in staff go straight to their dashboard.
 *   "/clinic"   the school clinic's own page (hours, services, appointments, advisories,
 *               FAQ, contact, team). Content: Administration > Website (App\Services\LandingContent).
 *   "/privacy"  the clinic's privacy notice.
 */
class LandingController extends Controller
{
    public function show(LandingContent $content): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('landing.index', [
            'page'    => $content->page(),
            'product' => ProductSite::content(),
        ]);
    }

    public function clinic(LandingContent $content): View
    {
        return view('landing.clinic', ['page' => $content->page()]);
    }

    public function privacy(LandingContent $content): View
    {
        return view('landing.privacy', ['page' => $content->page()]);
    }
}
