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

use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\AbstractAssessment;

class Model extends AbstractAssessment
{
    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getTypeName()
    {
        return 'Quiz';
    }

    /**
     * @return float
     */
    public function getMaxMark()
    {
        // TODO replicate this logic with objects when they become available
        $totalMarksAvailable = 0.0;
        foreach ($this->getSections() as $section) {
            foreach ($section as $question) {
                $totalMarksAvailable += $question['marks'];
            }
        }
        return $totalMarksAvailable;
    }

    /**
     * Stub method.
     * TODO - Replace with real method.
     */
    protected function getSections()
    {
        return [
            [
                ['marks' => 1],
            ],
            [
                ['marks' => 1], ['marks' => 1],
            ],
            [
                ['marks' => 1.5], ['marks' => 2.5],
            ],
            [
                ['marks' => 3.75], ['marks' => 1.25],
            ]
        ];
    }
}
