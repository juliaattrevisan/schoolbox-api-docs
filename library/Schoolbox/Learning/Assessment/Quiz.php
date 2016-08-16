<?php
/**
 * Quiz
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;


class Quiz extends AbstractModel implements IAssessable
{

    /**
     * All the Responses to this Quiz
     *
     * @var array
     */
    protected $allResponses = [];

    /**
     * Quiz constructor.
     */
    public function __construct()
    {
    }

    /**
     * Calculate the final mark for this IAssessable
     */
    public function calculateMark()
    {
        $sum = 0;

        foreach ($this->getAllResponses() as $currentResponse) {
            $sum += $currentResponse['mark'];
        }

        return $sum;
    }

    /* Getters/Setters
     */

    /**
     * @return array
     */
    public function getAllResponses()
    {
        return $this->allResponses;
    }

    /**
     * @param array $allResponses
     */
    public function setAllResponses($allResponses)
    {
        $this->allResponses = $allResponses;
    }
}
