<?php

namespace App\Support;

/**
 * Homepage "What's in the glass" (mockup home/index CUPS): three best sellers drawn as layered glasses.
 * Names and prices come from the menu, so a price change shows up here too.
 */
class Showcase
{
    /**
     * Layers bottom to top: [what, how much, height as % of the glass, colour, extra CSS class].
     *
     * @return array<int, array{code: string, tab: string, note: string, name: string, price: float, layers: array}>
     */
    public static function cups(): array
    {
        $cups = [
            ['code' => 'spanish', 'tab' => 'Spanish Latte', 'note' => 'Iced · our best seller', 'layers' => [
                ['Condensed milk', '30 ml', 12, '#D2A25A', ''], ['Fresh milk', '150 ml', 44, '#EEE2CF', ''], ['Espresso', '2 shots', 24, '#5B3521', ''], ['Milk foam', '', 10, '#F7EFE3', ''],
            ]],
            ['code' => 'matcha', 'tab' => 'Matcha Latte', 'note' => 'Ceremonial-grade matcha', 'layers' => [
                ['Fresh milk', '200 ml', 46, '#F0E6D4', ''], ['Matcha', '6 g whisked', 34, '#7E9B4F', ''], ['Matcha foam', '', 10, '#E3E8C8', ''],
            ]],
            ['code' => 'brownsugar', 'tab' => 'Brown Sugar', 'note' => 'Warm pearls, cold milk', 'layers' => [
                ['Tapioca pearls', '60 g', 16, '#3A2214', 'pearls'], ['Brown sugar syrup', '40 ml', 14, '#8E4F21', ''], ['Fresh milk', '200 ml', 58, '#F1E6D5', ''],
            ]],
        ];

        $out = [];
        foreach ($cups as $cup) {
            if ($item = DemoData::item($cup['code'])) {
                $out[] = $cup + ['name' => $item['name'], 'price' => $item['price']];
            }
        }

        return $out;
    }
}
