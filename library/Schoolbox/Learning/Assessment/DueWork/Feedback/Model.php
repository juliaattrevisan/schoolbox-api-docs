<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\DueWork\Review;

use \Schoolbox\Learning\Assessment\IFeedback;

class Model implements IFeedback
{
    /**
     * Mark given to this Assessable
     *
     * @var float
     */
    protected $mark;

    /* Getters/Setters
     */

    /**
     * @return float
     */
    public function getMark()
    {
        return $this->mark;
    }

    /**
     * @param float $mark
     */
    public function setMark($mark)
    {
        $this->mark = $mark;
    }

    /**
     * Calculate the final mark for this IAssessable
     */
    public function calculateMark()
    {
        return $this->getMark();
    }

    /**
     * This IAssessable has a mark value of null
     *
     * @return bool
     */
    public function isNullMark()
    {
        return $this->calculateMark() === null;
    }
}