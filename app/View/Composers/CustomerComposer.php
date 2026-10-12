<?php

namespace App\View\Composers;

use Illuminate\View\View;

/** Shares the scanned-QR session with every customer screen: $qr and the header pill text. */
class CustomerComposer
{
    public function compose(View $view): void
    {
        $qr = session('qr');

        $view->with([
            'qr' => $qr,
            'sessionLabel' => match ($qr['type'] ?? null) {
                'dine-in' => "TABLE {$qr['table']} · DINE-IN",
                'counter' => 'COUNTER ORDER',
                default => '',
            },
        ]);
    }
}
