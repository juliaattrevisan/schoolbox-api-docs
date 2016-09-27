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
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\User;

class Model extends AbstractAssessment
{
    /**
     * Sum of all marks available across all questions in this Quiz
     *
     * @var float
     */
    private $maxMark;

    /**
     * Whether this quiz potentially has different available marks per attempt.
     * If so, we must pass a QuizResponse to the MarkScheme when calculating marks.
     * This is determined on construction by evaluating whether (section.pool) < count(question.*) GROUP BY section_id
     *
     * @var bool
     */
    private $hasFixedTotalMarks;


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
        if(!is_null($gradeGroup)){
            throw new \InvalidArgumentException('Quiz cannot currently have mark type of Letter Grade.');
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function getHasFixedTotalMarks()
    {
        return $this->hasFixedTotalMarks;
    }

    /**
     * @param bool $hasFixedTotalMarks
     * @return static
     */
    public function setHasFixedTotalMarks($hasFixedTotalMarks)
    {
        $this->hasFixedTotalMarks = $hasFixedTotalMarks;
        return $this;
    }

    /**
     * @param User $user
     * @param bool $publishedOnly
     * @return null|string
     */
    public function getHumanReadableMark(User $user, $publishedOnly)
    {
        $markScheme = $this->getMarkScheme();

        // Special Case: If a Quiz has sections with questions pools, the total marks available may change per attempt
        if($markScheme instanceof TotalMarkFractional && !$this->getHasFixedTotalMarks()) {
            $participant = $this->getParticipant($user);

            $feedback = $publishedOnly
                ? $participant->findMostRecentPublishedFeedback($this)
                : $participant->findMostRecentFeedback($this);

            $response = $feedback
                ? $feedback->getResponse()
                : null;

            return $markScheme->getHumanReadableMark($this->getNormalisedMark($user, $publishedOnly), $response);
        }

        return parent::getHumanReadableMark($user, $publishedOnly);
    }
}
