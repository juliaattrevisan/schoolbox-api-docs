<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use InvalidArgumentException;
use RangeException;

/**
 * Permits granulation of 1/4 marks. eg 3.75 out of 5
 */
class TotalMark extends AbstractMarkScheme
{
    /** @var int */
    private $totalMark;

    /**
     * @param int $totalMark
     */
    public function __construct($totalMark)
    {
        $this->setTotalMark($totalMark);
    }

    /**
     * Returns a string representation of a normalised Mark
     *
     * Granularity to 1/4 of a mark
     *
     * @param float $normalisedMark
     * @throws InvalidArgumentException if normalised mark is outside valid range
     * @return string
     */
    public function getHumanReadableMark($normalisedMark)
    {
        $this->validateNormalisedMark($normalisedMark);

        $rounded = (round($normalisedMark * 4)) / 4;
        $total = $this->getTotalMark();

        return "$rounded / $total";
    }

    /**
     * Converts string fraction to float.
     *
     * @param string $humanReadableMark eg '25 / 35'
     * @return float
     */
    public function getNormalisedMark($humanReadableMark)
    {
        $matches = [];
        $pattern = '^\s*(?:([0-9.]+)(?:\s*\/\s*[0-9]+))';
        if(!preg_match($pattern, $humanReadableMark, $matches)){
            throw new InvalidArgumentException("Unable to parse total mark: $humanReadableMark");
        }

        $floatVal = floatval($matches[1]);
        $this->validateNormalisedMark($floatVal);
        return $floatVal;
    }

    /* Getters / Setters
     */

    /**
     * @return int
     */
    protected function getTotalMark()
    {
        return $this->totalMark;
    }

    /**
     * Total mark cannot have fractional part
     *
     * @param int $totalMark
     * @return static
     */
    protected function setTotalMark($totalMark)
    {
        $intVal = intval($totalMark);
        if($totalMark != $intVal){
            throw new InvalidArgumentException("Total mark cannot have fractional part: $totalMark");
        }

        if($intVal < 1){
            throw new RangeException("Total mark must be greater than or equal to 1: $totalMark");
        }
        $this->totalMark = $intVal;
        return $this;
    }

}