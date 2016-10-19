<?php
/**
 * Map
 *
 * A pre-built collection of all available strats to save you
 * having to build it yourself at compile time
 *
 * Simulates a db lookup table
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

class Mapping
{
    /**
     * @var array
     */
    private $map = [];

    /**
     * StrategyCollection constructor.
     *
     * Hardcode a bunch of summators in a particular order
     */
    public function __construct()
    {
        $this->setMap([
            Factory::WEIGHTED_AVERAGE => SummatorWeightedAverage::getTitle(),
            Factory::AVERAGE => SummatorAverage::getTitle(),
            Factory::MAXIMUM => SummatorMaximum::getTitle(),
            Factory::MINIMUM => SummatorMinimum::getTitle(),
        ]);
    }

    /**
     * Decompose an instance
     *
     * @param ISummator $summator
     * @throws \InvalidArgumentException
     * @return string
     */
    public function decompose(ISummator $summator)
    {
        if ($summator instanceof WeightedAverage) {
            return Factory::WEIGHTED_AVERAGE;
        }
        elseif ($summator instanceof Average) {
            return Factory::AVERAGE;
        }
        elseif ($summator instanceof Maximum) {
            return Factory::MAXIMUM;
        }
        elseif ($summator instanceof Minimum) {
            return Factory::MINIMUM;
        }

        throw new \InvalidArgumentException('choose a valid summation option');
    }

    /* Getters/Setters
     */

    /**
     * @return array
     */
    public function getMap()
    {
        return $this->map;
    }

    /**
     * @param array $map
     */
    private function setMap($map)
    {
        $this->map = $map;
    }
}