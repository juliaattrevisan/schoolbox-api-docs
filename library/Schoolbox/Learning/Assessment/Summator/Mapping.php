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
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Summator;

use \Schoolbox\Learning\Assessment\Summator\WeightedAverage as SummatorWeightedAverage;
use \Schoolbox\Learning\Assessment\Summator\Average as SummatorAverage;
use \Schoolbox\Learning\Assessment\Summator\Maximum as SummatorMaximum;
use \Schoolbox\Learning\Assessment\Summator\Minimum as SummatorMinimum;
use Schoolbox\Learning\Assessment\Summator\Factory as SummatorFactory;

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
            SummatorFactory::WEIGHTED_AVERAGE => SummatorWeightedAverage::getTitle(),
            SummatorFactory::AVERAGE => SummatorAverage::getTitle(),
            SummatorFactory::MAXIMUM => SummatorMaximum::getTitle(),
            SummatorFactory::MINIMUM => SummatorMinimum::getTitle(),
        ]);
    }

    /**
     * Decompose an instance
     *
     * @param ISummator $summator
     * @throws \InvalidArgumentException
     * @return string
     */
    public function decompose(ISummator $summator = null): string
    {
        if ($summator instanceof WeightedAverage) {
            return SummatorFactory::WEIGHTED_AVERAGE;
        }
        elseif ($summator instanceof Average) {
            return SummatorFactory::AVERAGE;
        }
        elseif ($summator instanceof Maximum) {
            return SummatorFactory::MAXIMUM;
        }
        elseif ($summator instanceof Minimum) {
            return SummatorFactory::MINIMUM;
        }

        throw new \InvalidArgumentException('Please choose a valid summation option');
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
