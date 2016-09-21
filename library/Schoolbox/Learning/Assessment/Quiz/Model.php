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

use \Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;

class Model extends AbstractAssessment
{
    /**
     * Sum of all marks available across all questions in this Quiz
     *
     * @var float
     */
    private $maxMark;


    public function __construct()
    {
        parent::__construct();
    }

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
        return $this->maxMark;
    }

    /**
     * @param float $marksAvailable
     * @return static
     */
    public function setMaxMark($marksAvailable)
    {
        $this->maxMark = $marksAvailable;
        return $this;
    }


    /**
     * @param GradeGroup|null $gradeGroup
     * @return static
     */
    public function setGradeGroup(GradeGroup $gradeGroup = null)
    {
        if(null !== $gradeGroup){
            throw new \InvalidArgumentException('Quiz cannot currently have mark type of Letter Grade.');
        }
        return $this;
    }
}
