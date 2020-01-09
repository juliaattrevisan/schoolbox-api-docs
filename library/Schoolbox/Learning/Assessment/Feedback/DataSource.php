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
 * The single source of truth for assessment feedback.
 *
 * This class is a composite of various instances of IFeedbackDataSource.
 */
class DataSource implements IFeedbackDataSource
{
    /**
     * @var IFeedbackDataSource[]
     */
    protected $subSources = [];

    /**
     * Adds a new source.
     *
     * @param IFeedbackDataSource $subSource
     * @return static
     */
    public function addSubSource(IFeedbackDataSource $subSource)
    {
        $this->subSources[] = $subSource;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function recognizes(IAssessable $assessment)
    {
        foreach ($this->subSources as $subSource) {
            if ($subSource->recognizes($assessment)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param IAssessable $assessment
     * @param User        $student If set, only return this student's responses
     * @return Collection|IFeedback[]
     */
    public function findForAssessment(IAssessable $assessment, User $student = null)
    {
        $assessments = new Collection();
        foreach ($this->subSources as $subSource) {
            if ($subSource->recognizes($assessment)) {
                $assessments = $assessments->merge($subSource->findForAssessment($assessment, $student));
            }
        }
        return $assessments;
    }

    /**
     * @param Collection|IAssessable[] $assessments
     * @param User                     $student If set, only return this student's responses
     * @return Collection|IFeedback[][]
     */
    public function findForAssessmentCollection(Collection $assessments, User $student = null)
    {
        $feedback = new Collection();
        foreach ($this->subSources as $subSource) {
            $feedback = $feedback->union($subSource->findForAssessmentCollection($assessments, $student));
        }
        return $feedback;
    }

    /**
     * @param User $student
     * @return Collection|IFeedback[]
     */
    public function findForStudent(User $student)
    {
        $allFeedback = new Collection();
        foreach ($this->subSources as $subSource) {
            $allFeedback = $allFeedback->merge($subSource->findForStudent($student));
        }
        return $allFeedback;
    }

    /**
     * @param IFeedback $feedback
     */
    public function save(IFeedback $feedback)
    {
        foreach ($this->subSources as $subSource) {
            if ($subSource->recognizes($feedback->getAssessable())) {
                $subSource->save($feedback);
                return;
            }
        }

        // TODO add markbook sync here
        // Quizzes need to be synced differently as they don't save thru this data source
    }

    /**
     * @param IFeedback $feedback
     */
    public function delete(IFeedback $feedback)
    {
        foreach ($this->subSources as $subSource) {
            if ($subSource->recognizes($feedback->getAssessable())) {
                $subSource->delete($feedback);
                return;
            }
        }

        // TODO add markbook sync here
    }


    /**
     * Creates a new model appropriate to the target assessment
     *
     * @param IAssessable $target
     * @return IFeedback
     */
    public function createModel(IAssessable $target)
    {
        /** @var IFeedback $feedback */
        $feedback = null;

        foreach ($this->subSources as $subSource) {
            if ($subSource->recognizes($target)) {
                $feedback = $subSource->createModel($target);
                $feedback->setAssessable($target);
            }
        }

        return $feedback;
    }

}
