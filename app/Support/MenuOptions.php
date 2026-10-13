<?php

namespace App\Support;

/** Checks the options picked for one menu item against that item's option groups. */
class MenuOptions
{
    /**
     * The form's picks as a flat list.
     *
     * @param  array<string, string|string[]>  $picked  group code => choice code(s)
     * @return array<int, array{group: string, choice: string}>
     */
    public static function flatten(array $picked): array
    {
        $out = [];
        foreach ($picked as $group => $choices) {
            foreach ((array) $choices as $choice) {
                $out[] = ['group' => (string) $group, 'choice' => (string) $choice];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string|string[]>  $picked  group code => choice code(s)
     * @return string|null what's wrong, or null when the choice is valid
     */
    public static function problem(array $item, array $picked): ?string
    {
        $groups = array_column($item['options'], null, 'code');

        if ($unknown = array_diff(array_keys($picked), array_keys($groups))) {
            return "{$item['name']} has no option “".implode(', ', $unknown).'”.';
        }

        foreach ($groups as $group) {
            $chosen = (array) ($picked[$group['code']] ?? []);

            if (array_diff($chosen, array_column($group['choices'], 'code'))) {
                return "Choose a valid {$group['name']} for {$item['name']}.";
            }
            if ($group['type'] === 'single' && count($chosen) > 1) {
                return "Choose only one {$group['name']} for {$item['name']}.";
            }
            if ($group['required'] && ! $chosen) {
                return "Choose a {$group['name']} for {$item['name']}.";
            }
        }

        return null;
    }
}
