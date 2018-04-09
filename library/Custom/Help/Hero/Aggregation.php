<?php
/**
 * Aggregation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Help\Hero;

class Aggregation
{

    /**
     * Aggregate data to work out who qualified for Hero status
     *
     * @param array $data
     */
    public function aggregate(array $data)
    {
        foreach ($data as $id => $currentData) {
            new \Alaress_Probe($currentData);
        }
    }
}