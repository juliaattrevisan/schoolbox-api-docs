<?php
/**
 * Quiz homepage component controller
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz\Component\Homepage;

use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends \Schoolbox\Learning\Assessment\Component\Homepage\Controller
{
    /**
     * @return string
     */
    protected function getTwigTemplate()
    {
        return '@learningAssessment/quiz/component.twig';
    }


    /**
     * Return whether or not this assessment is of the relevant type for the current controller
     *
     * @param IAssessable $assessment
     * @return bool
     */
    protected function filterAssessment(IAssessable $assessment)
    {
        return $assessment instanceof Quiz\Model;
    }

    /**
     * @return string
     */
    protected function getAssessmentType()
    {
        return AssessmentType::TYPE_QUIZ;
    }
}
