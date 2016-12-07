<?php
/**
 * Task homepage component controller
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task\Component\Homepage;

use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends \Schoolbox\Learning\Assessment\Component\Homepage\Controller
{
    /**
     * @return string
     */
    protected function getTwigTemplate()
    {
        return '@learningAssessment/task/component.twig';
    }

    /**
     * @return string
     */
    protected function getAssessmentType()
    {
        return AssessmentType::TYPE_TASK;
    }
}
