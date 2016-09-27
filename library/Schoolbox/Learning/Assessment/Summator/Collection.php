<?php
/**
 * Collection
 *
 * A pre-built collection of all available strats to save you
 * having to build it yourself at compile time
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

class Collection extends \Schoolbox\Collection
{
    const WEIGHTED_AVERAGE = 0;
    const AVERAGE = 1;
    const MAXIMUM = 2;
    const MINIMUM = 3;

    /**
     * Collection constructor.
     *
     * Hardcode a bunch of summators in a particular order
     *
     * @param array $stack
     */
    public function __construct($stack = array())
    {
        $this->setOffset(self::WEIGHTED_AVERAGE, new SummatorWeightedAverage());
        $this->setOffset(self::AVERAGE, new SummatorAverage());
        $this->setOffset(self::MAXIMUM, new SummatorMaximum());
        $this->setOffset(self::MINIMUM, new SummatorMinimum());
    }
}