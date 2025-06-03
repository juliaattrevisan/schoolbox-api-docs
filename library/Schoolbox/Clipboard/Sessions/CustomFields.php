<?php

namespace Schoolbox\Clipboard\Sessions;

use DebugBar\DebugBarException;

class CustomFields
{
    /**
     * Clipboard can allow multiple list prefixes: sessions and fixtures were ones we
     * actually knew about, but discovered Teams could also be returned here, while in
     * prod.
     */
    private const KNOWN_LIST_PREFIXES = ['sessions', 'fixtures', 'teams'];

    /**
     * Split the giant pile of $customFieldsMetadata into two distinct lists
     * which can be individually sorted. Clipboard intends to open this to
     * user customisation at some point
     *
     * @param array $customFieldsMetadata
     * @return array
     * @throws DebugBarException
     */
    public function generate(array $customFieldsMetadata): array
    {
        $temp = [];

        /* Precompute available list prefixes, setting up a data payload
         */
        foreach ($customFieldsMetadata as $id => $data) {
            foreach (self::KNOWN_LIST_PREFIXES as $prefix) {
                if (str_contains($id, "{$prefix}_")) {
                    $temp[$prefix][$id] = $data['showAfterField'];
                }
            }
        }

        /* Return all the mini-lists in one big struct, already split and sorted
         */
        $output = [];

        foreach (self::KNOWN_LIST_PREFIXES as $key) {
            /* One or more of these keys didn't have a set of data in the custom fields
             * Skip it
             */
            if (!array_key_exists($key, $temp)) {
                continue;
            }

            $nodes = $this->sorter($temp[$key]);

            foreach ($nodes as $current => $previous) {
                $output[$key][$current] = $customFieldsMetadata[$current];
            }
        }

        return $output;
    }

    /**
     * Sorts a set of custom fields by their showAfterField node
     *
     * Yoinked directly from
     * https://stackoverflow.com/questions/67056611/php-associative-array-sorting-by-previous-value
     */
    private function sorter(array $array): array
    {
        // find the key which doesn't have a matching value
        $unique = array_diff(array_keys($array), $array);
        $key = reset($unique);
        $value = $array[$key];
        $result = [$key => $value];
        // follow the values backwards until the key matches the value
        while ($key != $value) {
            $key = $array[$key] ?? null;
            $value = $array[$key] ?? null;
            $result = [$key => $value] + $result;
        }

        /* Remove dead elements: we don't need empty nodes etc.
         */
        return array_filter($result);
    }
}
