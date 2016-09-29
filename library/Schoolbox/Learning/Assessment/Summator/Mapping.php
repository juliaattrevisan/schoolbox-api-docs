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