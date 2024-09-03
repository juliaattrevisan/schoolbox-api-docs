<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

class Concattery
{
    /**
     * Deals with $input containing single- and double-quotes which xpath can't figure out
     * This means you have to do some preprocessing
     *
     * @param string $input
     * @return string
     */
    public function generate(string $input): string
    {
        $concat = $input;

        /* Replace ' with a concat equivalent
         * Just replace single quotes with an escaped version
         *
         * This is used specifically when a string has single quotes,
         * but not a double quote.
         */
        if (str_contains($concat, "'") && !str_contains($concat, '"')) {
            return '"' . str_replace("'", "'", $concat) . '"';
        }

        /* Replace " with a concat equivalent
         * This supports mixed usages e.g. a string containing both " and '
         */
        if (str_contains($concat, '"')) {
            /* First, explode on double quotes
             */
            $bits = explode('"', $concat);

            /* Then implode on \'"\'
             */
            $concat = '"' . implode("\", '\"', \"", $bits) . '"';
        }

        /* If it didn't contain any quotes, return the original string
         */
        if ($concat === $input) {
            return $input;
        }

        return "concat({$concat})";
    }
}
