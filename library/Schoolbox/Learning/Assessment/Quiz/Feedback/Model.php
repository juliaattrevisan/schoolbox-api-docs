<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Quiz\Feedback;

use \Schoolbox\Learning\Assessment\AbstractFeedback;

class Model extends AbstractFeedback
{
    /**
     * All the Responses to this Quiz
     *
     * These are Students' Responses to individual Questions
     * that were asked in the Quiz's Sections
     *
     * @var array
     */
    protected $allResponses = [];

    /**
     * Calculate the final mark for this IAssessable
     */
    public function getNormalisedMark()
    {
        $sum = 0;
        $total = 0;

        foreach ($this->getAllResponses() as $currentResponse) {
            $sum += $currentResponse['mark'];
            $total += $currentResponse['available'];
        }

        return 100 * (float)$sum / (float)$total;
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
     * @return self
     */
    public function setAllResponses($allResponses)
    {
        $this->allResponses = $allResponses;
        return $this;
    }
}