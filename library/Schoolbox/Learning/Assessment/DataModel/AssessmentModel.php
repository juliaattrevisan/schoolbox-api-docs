<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

/**
 * A trait for base assessment models - allows easy storage and retrieval of
 * the base assessment.
 */
trait AssessmentModel
{
    /**
     * The base assessable model that is a part of this model.
     *
     * @var Model
     */
    protected $assessmentModel;

    /**
     * @return Model
     */
    public function getAssessmentModel()
    {
        return $this->assessmentModel;
    }

    /**
     * @param Model $assessmentModel
     * @return static
     */
    public function setAssessmentModel(Model $assessmentModel)
    {
        $this->assessmentModel = $assessmentModel;
        return $this;
    }
}
