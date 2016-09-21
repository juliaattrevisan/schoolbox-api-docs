<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Quiz\Attempt;

use \Schoolbox\Learning\Assessment\AbstractResponse;

class Model extends AbstractResponse
{
    /**
     * Responses given in this attempt, along with marks available
     *
     * @var array $allResponses
     * @var float $allResponses['mark']       Mark given to student for a question
     * @var float $allResponses['available']  Marks available for a question
     */
    protected $allResponses = [];


    /**
     * Attempts are submitted at the updatedAt value
     * in case the Attempt is modified by the Student
     *
     * @return \Alaress_Date_Format
     */
    public function getSubmittedAt()
    {
        return $this->getUpdatedAt();
    }

    /**
     * @return float
     */
    public function getMarksAvailable()
    {
        $marksAvailable = 0.0;
        foreach ($this->allResponses as $response) {
            $marksAvailable += $response['available'];
        }
        return $marksAvailable;
    }


    /**
     * @return array
     */
    public function getAllResponses()
    {
        return $this->allResponses;
    }

    /**
     * @param array $allResponses
     * @return static
     */
    public function setAllResponses(array $allResponses)
    {
        $this->allResponses = $allResponses;
        return $this;
    }
}