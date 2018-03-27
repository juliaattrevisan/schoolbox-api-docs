<?php
/**
 * Aggregation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Stk\NCEA;

class Aggregation
{

    /**
     * @param $data
     */
    public function aggregate($data)
    {
        new \Alaress_Probe($data);
    }
}