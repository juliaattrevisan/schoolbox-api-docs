<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Scale;

class Factory
{

    /**
     * The allowed scaleOptions for a user to choose
     *
     * This controls how timestamps are grouped together, this increasing or decreasing the scale
     * of the dataset in exchange for perhaps more meaningful interpretations.
     *
     * For example, 60 seconds (scale=60) might be too granular for some people.
     * Such might prefer collating five minutes worth of data instead (scale=300)
     *
     * Events that occur within $scale seconds of each other are aggregated together.
     *
     * @var array
     */
    public const SCALE_OPTIONS = [
        'hour'      => 60,
        'day'       => 60*24,
        'month'     => 60*24*31,
    ];


    /**
     * Default scale when the specified one doesn't exist
     */
    private const DEFAULT_SCALE = 'day';

    /**
     * Enforce a default value
     *
     * @param $scale
     * @return string
     */
    public static function assertScale(?string $scale): string
    {
        if ((null === $scale) || ('' === $scale)) {
            return self::DEFAULT_SCALE;
        }

        return array_key_exists($scale, self::SCALE_OPTIONS) ? $scale : self::DEFAULT_SCALE;
    }


    /**
     * @param null|string $scale
     * @return IScale
     */
    public function produce(?string $scale): IScale
    {
        switch ($scale) {
            case '10min':
                return new Minute10();
            case '30min':
                return new Minute30();
            case 'hour':
                return new Hour();
            case 'month':
                return new Month();

            /* Use a daily view by default if an unknown setting was given
             */
            default:
                return new Day();
        }
    }
}
