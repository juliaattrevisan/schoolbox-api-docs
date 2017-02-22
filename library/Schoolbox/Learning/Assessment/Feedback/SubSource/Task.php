<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Feedback\SubSource;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Task\Feedback\MapperProxy;
use Schoolbox\Learning\Assessment\Task\Model as TaskObject;
use Schoolbox\User;

/**
 * Sub-source for task responses.
 */
class Task extends AbstractSubSource
{
    /**
     * @inheritdoc
     */
    protected $studentField = 'student_id';

    /**
     * Constructor
     *
     * @param MapperProxy $mapperProxy
     */
    public function __construct(MapperProxy $mapperProxy)
    {
        parent::__construct($mapperProxy);
    }

    /**
     * @inheritdoc
     */
    public function recognizes(IAssessable $assessment)
    {
        return $assessment instanceof TaskObject;
    }


    /**
     * @param IAssessable $assessment
     * @param User        $student If set, only return this student's responses
     * @return Collection|IFeedback[]
     */
    public function findForAssessment(IAssessable $assessment, User $student = null)
    {
        if ($this->recognizes($assessment)) {
            // Only fetch for student if provided
            $columns = ['assessment_id' => $assessment->getId(),];
            if ($student instanceof User) {
                $columns[$this->studentField] = $student->getId();
            }

            return $this->mapperProxy->findByColumnList(['assessment_id' => $assessment->getId()]);
        }
        return new Collection();
    }

    /**
     * Add assessment into each feedback
     *
     * @param Collection|IFeedback[][] $feedbacks
     * @param Collection|IAssessable[] $assessments
     * @return Collection
     */
    protected function linkFeedbacksToAssessments(Collection $feedbacks, Collection $assessments)
    {
        // Task Feedbacks link to assessment via TaskResponse
        return $assessments;
    }
}
