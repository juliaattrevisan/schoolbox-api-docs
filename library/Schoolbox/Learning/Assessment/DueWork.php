<?php
/**
 * DueWork
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;


class DueWork extends AbstractModel implements IAssessable
{
    /**
     * Mark given to this Assessable
     *
     * @var float
     */
    protected $mark;

    /**
     * DueWork constructor.
     */
    public function __construct()
    {
    }

    /**
     * Calculate the final mark for this IAssessable
     */
    public function calculateMark()
    {
        return $this->getMark();
    }

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
}