<?php

// Brew & Bite Café — business settings (from the mockup's BB_DATA and document.md §1).

return [

    'name' => 'LaraBai Baristro',
    'address' => 'F. Tañedo St., San Nicolas, Tarlac City, Tarlac',
    'hours' => 'Mon–Sun · 7:00 AM – 10:00 PM',
    'phone' => '0917 555 0142',
    'facebook' => 'LaraBai Baristro Tarlac',

    'delivery' => [
        'fee' => 50,
        'area' => 'Within Tarlac City only',
    ],

    // Senior Citizen / PWD: remove 12% VAT from the eligible share, then take 20% off it.
    'senior_pwd' => [
        'percent' => 20,
        'vat' => 0.12,
    ],

    // Demo-only features: system navigator, demo login accounts, "Reset demo data".
    'demo_mode' => (bool) env('CAFE_DEMO_MODE', true),

    // Seed today's 13 sample orders and 30 days of sales history. Turn off for a fresh start: the demo
    // accounts, menu, stock and tables are still seeded, but there are no orders and the stock starts from a count.
    'sample_orders' => (bool) env('CAFE_SAMPLE_ORDERS', true),

];
