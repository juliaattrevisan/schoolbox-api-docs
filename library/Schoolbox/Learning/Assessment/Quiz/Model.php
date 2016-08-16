<?php
/**
 * Quiz
 *
 * An Adapter class to wrap around the Quiz handler which sadly
 * uses array structures. This Adapter empowers it and makes it
 * adapt to the current IAssessable behaviours
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Quiz;

use \Schoolbox\Learning\Assessment\AbstractModel;

class Model extends AbstractModel
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
