<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\URL;

/** QR codes for the tables and the counter (document.md §3.1): signed links, drawn as inline SVG. */
class QrCodes
{
    /** The link a table's QR code opens. Signed, so the table number can't be edited by hand. */
    public static function tableUrl(int $table): string
    {
        return URL::signedRoute('customer.session.table', ['table' => $table]);
    }

    /** The counter QR starts a take-out / delivery session. */
    public static function counterUrl(): string
    {
        return URL::signedRoute('customer.session.counter');
    }

    /** Inline <svg> markup for any text; dark modules are styled by .qr-sheet in additions.css. */
    public static function svg(string $data): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'drawLightModules' => false,
        ]);

        // Drop the XML declaration so the markup can sit inside an HTML page
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', (new QRCode($options))->render($data));
    }
}
