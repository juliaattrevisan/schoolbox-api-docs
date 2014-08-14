<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Lumberjack;


class MicrotimeProcessor {

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