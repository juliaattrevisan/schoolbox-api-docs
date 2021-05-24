<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Feedback\SubSource;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Task\Feedback\MapperProxy;
use Schoolbox\Learning\Assessment\Task\Model as TaskObject;

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
     * Link IFeedback back to IAssessable
     *
     * @param Collection|IFeedback[] $feedbacks
     * @param IAssessable $assessment
     * @return Collection
     */
    protected function linkFeedbacksToAssessment(Collection $feedbacks, IAssessable $assessment)
    {
        // You cannot set a TaskFeedback's Task, it is derived from the TaskResponse
        return $feedbacks;
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
