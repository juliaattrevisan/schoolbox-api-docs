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

class Factory
{
    /**
     * @var int
     */
    protected $summator;

    /**
     * Factory constructor.
     * @param $summator
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
        $summatorCollection = new StrategyCollection();

        $strategy = $summatorCollection->getOffset($this->getSummator());

        if ($strategy instanceof ISummator) {
            return $strategy;
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