<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Summator;

/**
 * Class Factory
 *
 * @package Schoolbox\Learning\Assessment\Summator
 */
class Factory
{
    const WEIGHTED_AVERAGE = 'weighted';
    const AVERAGE = 'avg';
    const MAXIMUM = 'max';
    const MINIMUM = 'min';

    /**
     * Factory constructor.
     *
     * @param string $summator
     * @param bool   $requireAllActivities Must a student attempt all activities? If so, they will receive zero for and
     *                                     assessment they've not attempted.
     */
    public function __construct(
        private string $summator,
        private bool $requireAllActivities,
    ) {
    }


    /**
     * Bang out a Summation strategy
     *
     * @return ISummator
     * @throws \InvalidArgumentException
     */
    public function produce(): ISummator
    {
        switch ($this->summator) {
            case self::WEIGHTED_AVERAGE:
                return new WeightedAverage($this->requireAllActivities);

            case self::AVERAGE:
                return new Average();

            case self::MAXIMUM:
                return new Maximum();

            case self::MINIMUM:
                return new Minimum();
        }

        throw new \InvalidArgumentException('Please choose a valid summation option');
    }
}
