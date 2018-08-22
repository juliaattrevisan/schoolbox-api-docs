<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Accuracy;

class Factory
{

    /**
     * The allowed accuracies for a user to choose
     *
     * This controls how timestamps are grouped together, this increasing or decreasing the accuracy
     * of the dataset in exchange for perhaps more meaningful interpretations.
     *
     * For example, 60 seconds (accuracy=60) might be too granular for some people.
     * Such might prefer collating five minutes worth of data instead (accuracy=300)
     *
     * Events that occur within $accuracy seconds of each other are aggregated together.
     *
     * @var array
     */
    public const ACCURACIES = [
        '10min'     => 10,
        '30min'     => 30,
        'hour'      => 60,
        'day'       => 60*24,
        'month'     => 60*24*31,
    ];

    /**
     * @param null|string $accuracy
     * @return IAccuracy
     */
    public function produce(?string $accuracy): IAccuracy
    {
        switch ($accuracy) {
            case '10min': return new Minute10();
            case '30min': return new Minute30();
            case 'hour' : return new Hour();
            case 'month': return new Month();

            /* Use a daily view by default if an unknown setting was given
             */
            default:
                return new Day();
        }
    }
}