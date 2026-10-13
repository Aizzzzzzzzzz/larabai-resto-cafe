<?php

namespace App\Support;

/**
 * The navigator's floor plan: each table seen from above (chairs around it, a QR standee in the middle)
 * and the take-out rider. Ported from the mockup's drawing code; the shapes follow the table's seats.
 * Everything returned is fixed SVG markup, safe to print unescaped.
 */
class FloorPlan
{
    /** @return array{shape: string, svg: string} for a table with this many seats */
    public static function table(int $seats): array
    {
        $shape = $seats <= 2 ? 'round2' : ($seats <= 4 ? 'square4' : 'long6');
        $qr = self::qr();

        $svg = match ($shape) {
            'round2' => self::chairL(24, 50).self::chairR(96, 50).'<circle class="fp-fill fp-ink" cx="60" cy="50" r="24"/>'.$qr,
            'square4' => self::chairT(60, 16).self::chairB(60, 84).self::chairL(22, 50).self::chairR(98, 50)
                .'<rect class="fp-fill fp-ink" x="36" y="28" width="48" height="44" rx="5"/>'.$qr,
            default => implode('', array_map(fn ($x) => self::chairT($x, 16).self::chairB($x, 84), [32, 60, 88]))
                .'<rect class="fp-fill fp-ink" x="16" y="30" width="88" height="40" rx="5"/>'.$qr,
        };

        return ['shape' => $shape, 'svg' => $svg];
    }

    /** The rider on a scooter, for take-out and delivery. */
    public static function rider(): string
    {
        return '<line class="fp-ink" x1="6" y1="40" x2="18" y2="40"/><line class="fp-ink" x1="2" y1="52" x2="13" y2="52"/>'
            .'<rect class="fp-fill fp-ink" x="34" y="24" width="24" height="20" rx="3"/>'
            .'<circle class="fp-ink fp-none" cx="30" cy="72" r="12"/><circle class="fp-ink fp-none" cx="94" cy="72" r="12"/>'
            .'<path class="fp-ink fp-none" d="M30 72h24l12-20h16l12 20M54 52H40M82 52l-6-18h-8"/>'
            .'<circle class="fp-fill fp-ink" cx="70" cy="22" r="7"/>';
    }

    // A chair is a seat square plus a backrest line on the side away from the table.
    private static function seat(int $x, int $y, int $w, int $h): string
    {
        return "<rect class=\"fp-fill fp-ink\" x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" rx=\"3\"/>";
    }

    private static function back(int $x1, int $y1, int $x2, int $y2): string
    {
        return "<line class=\"fp-ink\" x1=\"{$x1}\" y1=\"{$y1}\" x2=\"{$x2}\" y2=\"{$y2}\"/>";
    }

    private static function chairL(int $cx, int $cy): string
    {
        return self::seat($cx - 6, $cy - 9, 12, 18).self::back($cx - 11, $cy - 11, $cx - 11, $cy + 11);
    }

    private static function chairR(int $cx, int $cy): string
    {
        return self::seat($cx - 6, $cy - 9, 12, 18).self::back($cx + 11, $cy - 11, $cx + 11, $cy + 11);
    }

    private static function chairT(int $cx, int $cy): string
    {
        return self::seat($cx - 9, $cy - 6, 18, 12).self::back($cx - 11, $cy - 11, $cx + 11, $cy - 11);
    }

    private static function chairB(int $cx, int $cy): string
    {
        return self::seat($cx - 9, $cy - 6, 18, 12).self::back($cx - 11, $cy + 11, $cx + 11, $cy + 11);
    }

    private static function qr(): string
    {
        return '<g transform="translate(51 41)"><rect class="fp-card fp-ink" width="18" height="18" rx="3" style="stroke-width:3"/>'
            .'<rect class="fp-dot" x="4.5" y="4.5" width="3.5" height="3.5"/><rect class="fp-dot" x="10" y="4.5" width="3.5" height="3.5"/>'
            .'<rect class="fp-dot" x="4.5" y="10" width="3.5" height="3.5"/><rect class="fp-dot" x="10.5" y="10.5" width="3" height="3"/></g>';
    }
}
