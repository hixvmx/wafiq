<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\BrandingImages;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Logo, brand colour, stamp and signature. */
class BrandingController extends Controller
{
    public function edit(CurrentCompany $current): Response
    {
        $company = $current->get();

        return Inertia::render('Settings/Branding', [
            'brandColor' => $company->preferences()->get('brand_color'),
            'images' => collect(Company::IMAGES)->mapWithKeys(fn (string $kind) => [$kind => $company->imageUrl($kind)]),
        ]);
    }

    public function update(Request $request, CurrentCompany $current): RedirectResponse
    {
        $data = $request->validate(['brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']]);

        $current->get()->preferences()->put(['brand_color' => strtolower($data['brand_color'])]);

        return back()->with('success', __('ui.settings.saved'));
    }

    public function upload(Request $request, string $kind, CurrentCompany $current, BrandingImages $images): RedirectResponse
    {
        abort_unless(in_array($kind, Company::IMAGES, true), 404);

        // No SVG: it can carry scripts.
        $request->validate(['image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096']]);

        $images->store($current->get(), $kind, $request->file('image'));

        return back()->with('success', __('ui.settings.saved'));
    }

    public function destroy(string $kind, CurrentCompany $current, BrandingImages $images): RedirectResponse
    {
        abort_unless(in_array($kind, Company::IMAGES, true), 404);

        $images->delete($current->get(), $kind);

        return back()->with('success', __('ui.settings.saved'));
    }

    /**
     * Streams a branding image. The logo is public (client page, emails, link previews);
     * the stamp and signature only for the company's members.
     */
    public function show(Request $request, Company $company, string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, Company::IMAGES, true), 404);
        abort_if($kind !== 'logo' && ! $request->user()?->roleIn($company), 404);

        $path = $company->getAttribute($kind);
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => ($kind === 'logo' ? 'public' : 'private').', max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
