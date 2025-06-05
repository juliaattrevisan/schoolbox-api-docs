<?php

namespace Schoolbox\Clipboard\Sessions;

class CustomFields
{
    /**
     * Safety mechanism to forcibly exit a while() loop
     * based on mutating data
     */
    private const MAX_ITERATIONS = 100;


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

        /* Try to find "anchor points" from Clipboard's ui.
         * These showAfterField values are part of their ui, not a custom field,
         * and represent potential starting points. In the example struct above,
         * "result" and "timeBlock" are their ui elements: everything starting
         * with fixtures_ is a custom field that somehow gets sequenced around
         * the ui elements "result" and "timeBlock".
         *
         * By the time you get here, $data has had its keys and values flipped,
         * to make traversing a bit more manageable.
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
            unset($data[$previous]);
        }

        /* Look for stuff in $data that we need to insert back into $stack
         */
        $n = 0;

        /*  Note that $data is a reductive data set
         */
        while (\count($data) && $n < self::MAX_ITERATIONS) {
            foreach ($data as $key => $value) {
                /* Otherwise, its predecessor was here, and $current goes in after that
                 */
                $i = array_search($key, $stack);

                /* $key is currently not in the $stack
                 * This means we haven't encountered its predecessor yet, because clipboard sent
                 * us a randomly-ordered set of fields. We'll have to circle back multiple times
                 * to fill in its predecessors first.
                 */
                if (false === $i) {
                    continue;
                }

                /* We know that $data now contains all the actual custom fields and that the tuples
                 * always work in pairs, like crabs: it's a "before" and "after" definition. That means
                 * we can exploit something about its nature to simplify our slicing and dicing
                 */
                array_splice($stack, $i + 1, 0, [$value]);

                /* Remove it from the $data then jump start the process again
                 */
                unset($data[$key]);
            }

            /* For Safety
             */
            $n++;
        }

        return $stack;
    }
}
