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
    private const KNOWN_LIST_PREFIXES = ['fixtures', 'sessions', 'teams'];

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

            $nodes = $this->sorter($key, array_flip($temp[$key]));

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
    private function sorter(string $index, array $data): array
    {
        $maxIterations = 100;
        $stack = [];

        /* Try to find "anchor points" from Clipboard's ui.
         * These showAfterField values are part of their ui, not a custom field,
         * and represent potential starting points. In the example struct below,
         * "result" and "timeBlock" are their ui elements: everything starting
         * with fixtures_ is a custom field that somehow gets sequenced around
         * the ui elements "result" and "timeBlock".
         *
         *
            Array
            (
                [fixtures_studentArrivalTime] => fixtures_busReturnTime
                [fixtures_busReturnTime] => fixtures_busDepartureTime
                [fixtures_mvpVote5] => result
                [fixtures_mvpVote1] => fixtures_mvpVote2
                [fixtures_mvpVote4] => fixtures_mvpVote5
                [fixtures_mvpVote3] => fixtures_mvpVote4
                [fixtures_busDepartureTime] => timeBlock
                [fixtures_mvpVote2] => fixtures_mvpVote3
                [fixtures_studentPickupTime] => fixtures_studentArrivalTime
            )

         * To dodge around this crazy, we can flip that array struct so instead of
         * (field, showAfterField) tuples, we have (showAfterField, field) tuples.
         * In the struct below, fixtures_mvpVote5 comes after result,
         * and fixtures_mvpVote4 comes after fixtures_mvpVote5 etc.
         *
            Array
            (
                [fixtures_busReturnTime] => fixtures_studentArrivalTime
                [fixtures_busDepartureTime] => fixtures_busReturnTime
                [result] => fixtures_mvpVote5
                [fixtures_mvpVote2] => fixtures_mvpVote1
                [fixtures_mvpVote5] => fixtures_mvpVote4
                [fixtures_mvpVote4] => fixtures_mvpVote3
                [timeBlock] => fixtures_busDepartureTime
                [fixtures_mvpVote3] => fixtures_mvpVote2
                [fixtures_studentArrivalTime] => fixtures_studentPickupTime
            )
         */
        foreach ($data as $previous => $next) {
            if (!str_contains($previous, "{$index}_")) {
                $stack[] = $previous;
                $stack[] = $next;

                /* Knock them off the list: we've already added them
                 */
                unset ($data[$previous]);
            }
        }

        new \Alaress_Probe('$data', '$stack');
        new \Alaress_Probe($data, $stack);
        new \Alaress_Probe('<hr>');

        /* We know that $data now contains all the actual custom fields and that the tuples
         * always work in pairs, like crabs: it's a "before" and "after" definition. That means
         * we can exploit something about its nature to simplify our splicing and dicing
         */

        /* Look for stuff in $data that we need to insert back into $stack
         */
        foreach ($data as $key => $value) {
            if (in_array($key, $stack)) {
                for ($i = 0; $i < count($stack); $i++) {
                    $current = $stack[$i];

                    if ($current === $key) {
                        $before = array_slice($stack, 0, $i + 1);
                        $after = array_slice($stack, $i + 1, count($stack));

                        new \Alaress_Probe($key, $before, $after);

                        /* Insert whatever comes after $current into position in the $stack
                         */
                        $before[] = $data[$current];

                        /* Join them back up
                         */
                        $stack = array_merge($before, $after);

                        /* Remove it from the $data then jump start the process again
                         */
                        unset($data[$current]);

                        break;
                    }
                }
            }
        }

new \Alaress_Probe($stack, $data);
        die();




//
//        foreach ($array as $key => $value) {
//            /* First of its kind
//             */
//            if (!isset($stack[$key])) {
//                $stack[$key] = $value;
//                continue;
//            }
//
//            while (\count($stack) !== \count($array()) && $n < $maxIterations) {
//                $n++;
//
//
//            }
//
//
//
//        }

        die();
    }
}
