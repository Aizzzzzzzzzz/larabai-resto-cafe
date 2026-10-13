<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandingRequest;
use App\Services\AuditLogger;
use App\Services\Branding;
use App\Services\MenuImages;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** The café's logo: shown in the headers, on the login and error pages and as the browser tab icon. */
class BrandingController extends Controller
{
    public function edit(): View
    {
        return view('admin.branding', ['logo' => Branding::logoUrl()]);
    }

    public function update(BrandingRequest $request, MenuImages $images): RedirectResponse
    {
        $old = Branding::logoPath();

        if ($request->hasFile('logo')) {
            $new = $images->store($request->file('logo'), Branding::DIRECTORY, Branding::MAX_SIDE, 'logo');
            Settings::set(Branding::SETTING, $new);
            $images->delete($old);
            AuditLogger::record('Changed logo', 'New logo uploaded', $request->user());

            return redirect()->route('admin.branding.edit')->with('status', 'The new logo is live.');
        }

        if ($request->boolean('remove_logo') && $old) {
            Settings::set(Branding::SETTING, null);
            $images->delete($old);
            AuditLogger::record('Reset logo', 'Back to the built-in mark', $request->user());

            return redirect()->route('admin.branding.edit')->with('status', 'The logo was reset to the built-in mark.');
        }

        return redirect()->route('admin.branding.edit')->with('status', 'Nothing changed. Choose a picture first.');
    }
}
