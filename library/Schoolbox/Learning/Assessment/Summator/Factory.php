<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Summator;

use \Schoolbox\Learning\Assessment\Summator\WeightedAverage as SummatorWeightedAverage;
use \Schoolbox\Learning\Assessment\Summator\Average as SummatorAverage;
use \Schoolbox\Learning\Assessment\Summator\Maximum as SummatorMaximum;
use \Schoolbox\Learning\Assessment\Summator\Minimum as SummatorMinimum;

class Factory
{
    const WEIGHTED_AVERAGE = 'weighted';
    const AVERAGE = 'avg';
    const MAXIMUM = 'max';
    const MINIMUM = 'min';

    /**
     * @var string
     */
    protected $summator;

    /**
     * Factory constructor.
     *
     * @param string $summator
     */
    public function __construct($summator)
    {
        $this->setSummator($summator);
    }

    /**
     * Bang out a Summation strategy
     *
     * @return ISummator
     */
    public function produce()
    {
        switch ($this->getSummator()) {
            case self::WEIGHTED_AVERAGE:
                return new SummatorWeightedAverage();

            case self::AVERAGE:
                return new SummatorAverage();

            case self::MAXIMUM:
                return new SummatorMaximum();

            case self::MINIMUM:
                return new SummatorMinimum();
        }

        throw new \InvalidArgumentException('choose a valid summation option');
    }

    /* Getters/Setters
     */

    /**
     * @return int
     */
    protected function getSummator()
    {
        return $this->summator;
    }

    /**
     * @param int $summator
     */
    protected function setSummator($summator)
    {
        $this->summator = $summator;
    }
}