<?php

namespace App\Services;

/** The café's logo: the owner's uploaded picture, or the built-in "LB" mark when there is none. */
class Branding
{
    public const SETTING = 'logo_path';

    public const DIRECTORY = 'branding';

    /** The logo is shown small, so it is kept to this many pixels on its longest side. */
    public const MAX_SIDE = 512;

    public static function logoPath(): ?string
    {
        return Settings::get(self::SETTING);
    }

    public static function logoUrl(): ?string
    {
        return MenuImages::url(self::logoPath());
    }
}
