<?php

namespace Schoolbox\Clipboard\Sessions;

class CustomFields
{
    /**
     * Clipboard can allow multiple list prefixes: sessions and fixtures were ones we
     * actually knew about, but discovered Teams could also be returned here, while in
     * prod.
     */
    private const KNOWN_LIST_PREFIXES = ['fixtures', 'sessions', 'teams'];


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

        /* Precompute available list prefixes, setting up a data payload
         * Here's an example struct for what $temp is going to be like.
         *
            Array
            (
                [fixtures][fixtures_studentArrivalTime] => fixtures_busReturnTime
                [fixtures][fixtures_busReturnTime] => fixtures_busDepartureTime
                [fixtures][fixtures_mvpVote5] => result
                [fixtures][fixtures_mvpVote1] => fixtures_mvpVote2
                [fixtures][fixtures_mvpVote4] => fixtures_mvpVote5
                [fixtures][fixtures_mvpVote3] => fixtures_mvpVote4
                [fixtures][fixtures_busDepartureTime] => timeBlock
                [fixtures][fixtures_mvpVote2] => fixtures_mvpVote3
                [fixtures][fixtures_studentPickupTime] => fixtures_studentArrivalTime
            )
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

            /* Compile data in a prescribed order, ignoring anything that's meaningless
             * such as looking for data corresponding to a clipboard ui element
             */
            $nodes = $this->sorter($key, array_flip($temp[$key]));

            foreach ($nodes as $current) {
                if (isset($customFieldsMetadata[$current])) {
                    $output[$key][$current] = $customFieldsMetadata[$current];
                }
            }
        }

        return $output;
    }

    /**
     * Sorts a set of custom fields by their showAfterField node
     */
    private function sorter(string $index, array $data): array
    {
        $stack = [];

        foreach (array_keys($data) as $anchor) {
            if (str_contains($anchor, "{$index}_")) {
                continue;
            }

            $current = $anchor;

            while ($current !== null) {
                $stack[] = $current;
                $next = $data[$current] ?? null;

                unset($data[$current]);
                $current = $next;
            }
        }

        return $stack;
    }
}
