<?php

namespace Schoolbox\Clipboard\Sessions;

class CustomFields
{
    /**
     * Split the giant pile of $customFieldsMetadata into two distinct lists
     * which can be individually sorted. Clipboard intends to open this to
     * user customisation at some point
     *
     * @param array $customFieldsMetadata
     * @return array
     */
    public function generate(array $customFieldsMetadata): array
    {
        $temp = [];

        foreach ($customFieldsMetadata as $id => $data) {
            if (str_contains($id, 'sessions_')) {
                $temp['sessions'][$id] = $data['showAfterField'];
            } elseif (str_contains($id, 'fixtures_')) {
                $temp['fixtures'][$id] = $data['showAfterField'];
            }
        }

        /* Return all the mini-lists in one big struct, already split and sorted
         */
        $output = [];

        foreach (['sessions', 'fixtures'] as $key) {
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
