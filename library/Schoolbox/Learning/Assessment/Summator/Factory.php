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
     * @var string
     */
    protected $summator;


    /**
     * Factory constructor.
     *
     * @param string $summator
     */
    public function __construct(string $summator)
    {
        $this->summator = $summator;
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
                return new WeightedAverage();

            case self::AVERAGE:
                return new Average;

            case self::MAXIMUM:
                return new Maximum();

            case self::MINIMUM:
                return new Minimum();
        }

        throw new \InvalidArgumentException('Please choose a valid summation option');
    }
}
