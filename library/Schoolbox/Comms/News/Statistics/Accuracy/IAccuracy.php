<?php
/**
 * IAccuracy
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Accuracy;

interface IAccuracy
{

    /**
     * Get the sql representing the accuracy of each strategy's setting
     *
     * @return string
     */
    public function getSql(): string;


    /**
     * Number of minutes to add when generating date placeholders in generateDateRange()
     *
     * @return int
     */
    public function getInterval(): int;
}