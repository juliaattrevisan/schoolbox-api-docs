<?php

namespace Schoolbox\Clipboard\Sessions;

class CustomFields
{
    /**
     * Safety mechanism to forcibly exit a while() loop
     * based on mutating data
     */
    private CONST MAX_ITERATIONS = 100;


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

        /* Try to find "anchor points" from Clipboard's ui.
         * These showAfterField values are part of their ui, not a custom field,
         * and represent potential starting points. In the example struct below,
         * "result" and "timeBlock" are their ui elements: everything starting
         * with fixtures_ is a custom field that somehow gets sequenced around
         * the ui elements "result" and "timeBlock".
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
         */
        foreach ($data as $previous => $next) {
            /* This is a custom field, not a clipboard ui anchor - skip past this for now
             * since we're looking for their ui anchors. These anchors are ancestors of the
             * custom fields somehow.
             */
            if (str_contains($previous, "{$index}_")) {
                continue;
            }

            $stack[] = $previous;
            $stack[] = $next;

            /* Knock them off the list: we've already added them
             */
            unset ($data[$previous]);
        }

        /* Look for stuff in $data that we need to insert back into $stack
         */
        $n = 0;

        /*  Note that $data is a reductive data set
         */
        while (\count($data) && $n < self::MAX_ITERATIONS) {
            foreach ($data as $key => $value) {
                /* $key is currently not in the $stack
                 * This means we haven't encountered its predecessor yet, because clipboard sent
                 * us a randomly-ordered set of fields. We'll have to circle back multple times
                 * to fill in its predecessors first.
                 */
                if (!in_array($key, $stack)) {
                    continue;
                }

                /* Otherwise, its predecessor was here, and $current goes in after that
                 */
                for ($i = 0; $i < count($stack); $i++) {
                    $current = $stack[$i];

                    /* Not the key we're looking for in the $stack - keep looking to find the place
                     * into which we need to insert $current
                     */
                    if ($current !== $key) {
                        continue;
                    }

                    /* We know that $data now contains all the actual custom fields and that the tuples
                     * always work in pairs, like crabs: it's a "before" and "after" definition. That means
                     * we can exploit something about its nature to simplify our slicing and dicing
                     */
                    $before = array_slice($stack, 0, $i + 1);
                    $after = array_slice($stack, $i + 1, count($stack));

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

            /* For Safety
             */
            $n++;
        }

        return $stack;
    }
}
