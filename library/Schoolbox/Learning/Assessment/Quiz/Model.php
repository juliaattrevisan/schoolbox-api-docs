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
use Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Learning\Assessment\Quiz\Section as QuizSection;

class Model extends AbstractAssessment
{
    /** @var Collection|QuizSection[] */
    private $sections;


    public function __construct()
    {
        parent::__construct();
        $this->sections = new Collection();
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
        $totalMarksAvailable = 0.0;
        foreach ($this->getSections() as $section) {
            foreach ($section->getQuestions() as $question) {
                $totalMarksAvailable += $question->getMarks();
            }
        }
        return $totalMarksAvailable;
    }


    /**
     * @return Collection|QuizSection[]
     */
    public function getSections()
    {
        return $this->sections;
    }

    /**
     * @param Collection|QuizSection[] $sections
     * @return $this
     */
    public function setSections(Collection $sections)
    {
        $this->sections = $sections;
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
