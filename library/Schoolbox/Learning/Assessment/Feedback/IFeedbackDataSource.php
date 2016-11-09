<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Feedback;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;

/**
 * Interface for all assessment response sources.
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
     * @return Collection|IFeedback[]
     */
    public function findForAssessment(IAssessable $assessment);

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
