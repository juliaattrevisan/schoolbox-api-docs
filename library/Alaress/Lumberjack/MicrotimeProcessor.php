<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Lumberjack;

class MicrotimeProcessor
{

    /**
     * @param array $record
     * @return array
     */
    function __invoke(array $record)
    {
        $t = microtime(true);

        $record['extra'] = array_merge(
            $record['extra'],
            array(
                'microtime' => $t - (int)$t,
            )
        );

        return $record;
    }
}
