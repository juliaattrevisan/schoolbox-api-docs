<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Feedback;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\User;

/**
 * Interface for all assessment feedback sources.
 */
interface IFeedbackDataSource
{
    /**
     * Does this class recognize this type of assessable?
     *
     * @param IAssessable $assessment
     * @return bool
     */
    public function recognizes(IAssessable $assessment);

    /**
     * Returns feedback for this assessment.
     *
     * @param IAssessable $assessment
     * @param User        $student If set, only return this student's responses
     * @return Collection|IFeedback[]
     */
    public function findForAssessment(IAssessable $assessment, User $student = null);

    /**
     * Returns feedback for all these assessments.
     *
     * @param Collection|IAssessable[] $assessments
     * @param User                     $student If set, only return this student's responses
     * @return Collection|IFeedback[][]
     */
    public function findForAssessmentCollection(Collection $assessments, User $student = null);

    /**
     * Returns feedback for work that this student has submitted.
     *
     * @param User $student
     * @return mixed
     */
    public function findForStudent(User $student);

    /**
     * Saves a feedback.
     *
     * @param IFeedback $feedback
     */
    public function save(IFeedback $feedback);

    /**
     * Soft-deletes a feedback.
     *
     * @param IFeedback $feedback
     */
    public function delete(IFeedback $feedback);


    /**
     * Creates a new model appropriate to the target assessment
     *
     * @param IAssessable $target
     * @return IFeedback
     */
    public function createModel(IAssessable $target);
}
