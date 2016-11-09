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
     * @return Collection|IFeedback[]
     */
    public function findForAssessment(IAssessable $assessment)
    {
        $assessments = new Collection();
        foreach ($this->subSources as $subSource) {
            if ($subSource->recognizes($assessment)) {
                $assessments = $assessments->merge($subSource->findForAssessment($assessment));
            }
        }
        return $assessments;
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
