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
        /* Replace " with a concat equivalent
         */
        if (str_contains($input, '"')) {
            /* First, explode on double quotes
             */

            /* Then implode on \'"\'
             */
        }

        /* Replace ' with a concat equivalent
         */
        if (str_contains($input, "'")) {
            /* First, explode on double quotes
             */

            /* Then implode on \'"\'
             */
        }

        /* If it didn't contain any quotes, return the original string
         */
        return '';
    }
}
