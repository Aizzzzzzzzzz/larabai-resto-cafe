<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Vertical bar chart drawn as inline SVG on the server (port of the mockup's BBChart.bars, minus
 * the JavaScript): grid lines, rounded-top bars, every-Nth label, today's bar highlighted, and
 * a native tooltip (<title>) on each bar.
 */
class BarChart extends Component
{
    public const WIDTH = 640;

    private const PAD = ['t' => 12, 'r' => 8, 'b' => 26, 'l' => 56];

    /** @var array<int, array{x: float, y: float, w: float, h: float, path: string, label: string, showLabel: bool, tip: string, hl: bool}> */
    public array $bars = [];

    /** @var array<int, array{y: float, text: string}> */
    public array $ticks = [];

    public int $base;

    /**
     * @param  array<int, array{label: string, value: int|float, tip?: string}>  $data
     * @param  string  $format  'peso' → ₱1.2k / ₱500, anything else → whole numbers
     */
    public function __construct(
        public array $data,
        public string $axisLabel = 'Bar chart',
        public int $height = 220,
        public ?int $highlight = null,
        public string $format = 'int',
    ) {
        $p = self::PAD;
        $iw = self::WIDTH - $p['l'] - $p['r'];
        $ih = $height - $p['t'] - $p['b'];
        $this->base = $p['t'] + $ih;

        $max = self::niceMax(max([0, ...array_column($data, 'value')]));
        $step = $iw / max(1, count($data));
        $bw = max(3, min(28, $step * 0.62));
        $every = (int) ceil(count($data) / max(1, floor($iw / 44)));
        $last = count($data) - 1;

        foreach (range(0, 4) as $i) {
            $this->ticks[] = ['y' => $p['t'] + $ih - $ih * $i / 4, 'text' => $this->axis($max * $i / 4)];
        }

        foreach ($data as $i => $d) {
            $h = $max ? $ih * $d['value'] / $max : 0;
            $x = $p['l'] + $step * $i + ($step - $bw) / 2;
            $y = $this->base - $h;
            $r = min(4, $bw / 2, $h);

            $this->bars[] = [
                'hit' => ['x' => $p['l'] + $step * $i, 'y' => $p['t'], 'w' => $step, 'h' => $ih],
                // Rounded top, square base
                'path' => $h > 0 ? sprintf('M%.2f,%d V%.2f Q%.2f,%.2f %.2f,%.2f H%.2f Q%.2f,%.2f %.2f,%.2f V%d Z', $x, $this->base, $y + $r, $x, $y, $x + $r, $y, $x + $bw - $r, $x + $bw, $y, $x + $bw, $y + $r, $this->base) : '',
                'labelX' => $p['l'] + $step * $i + $step / 2,
                'label' => $d['label'],
                'showLabel' => $i === $last || ($i % $every === 0 && $last - $i >= $every),
                'tip' => trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('</b>', ' · </b>', $d['tip'] ?? $d['label'].' · '.$this->axis($d['value'], true))))),
                'hl' => $highlight === $i,
            ];
        }
    }

    /** A "nice" top for the axis: 1, 2, 2.5, 5 or 10 × a power of ten. */
    public static function niceMax(float $v): float
    {
        if ($v <= 0) {
            return 1;
        }
        $p = 10 ** floor(log10($v));
        $n = $v / $p;

        return ($n <= 1 ? 1 : ($n <= 2 ? 2 : ($n <= 2.5 ? 2.5 : ($n <= 5 ? 5 : 10)))) * $p;
    }

    private function axis(float $v, bool $full = false): string
    {
        if ($this->format === 'peso') {
            return $full ? peso($v) : '₱'.($v >= 1000 ? round($v / 1000).'k' : round($v));
        }

        return (string) round($v);
    }

    public function render(): View
    {
        return view('components.bar-chart');
    }
}
