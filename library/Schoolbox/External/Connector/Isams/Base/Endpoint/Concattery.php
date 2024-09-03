<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Connector\Isams\Base\Endpoint;

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

        /* Replace " with a concat equivalent
         */
        if (str_contains($concat, '"')) {
            /* First, explode on double quotes
             */
            $bits = explode('"', $concat);

            /* Then implode on \'"\'
             */
            $concat = implode('\'"\'', $bits);
        }

        /* Replace ' with a concat equivalent
         * Just replace single quotes with an escaped version
         */
        if (str_contains($concat, "'")) {
            $concat = str_replace("'", "\'", $concat);
        }

        /* If it didn't contain any quotes, return the original string
         */
        return $concat;
    }
}
