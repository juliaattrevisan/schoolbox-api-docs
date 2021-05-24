<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

/**
 * Interface for data model representations of an instance of
 * `\Schoolbox\Learning\Assessment\IAssessable`.
 *
 * All of these data models will contain a `\Schoolbox\Learning\Assessment\DataModel\Model`
 * instance.
 */
interface IAssessableDataModel
{
    /**
     * Gets ID of the data model itself.
     *
     * This is the ID of the specific data model, not the ID of $this->getAssessmentModel().
     *
     * @return int
     */
    public function getId();

    /**
     * @param int $id
     * @return static
     */
    public function setId($id);

    /**
     * Retrieves the base assessment model for this data model.
     *
     * This would be called `getAssessment`, but this name conflicts with the
     * task model's method of the same name.
     *
     * @return Model
     */
    public function getAssessmentModel();

    /**
     * Stores the base assessment model for this data model.
     *
     * This would be called `setAssessment`, but this name conflicts with the
     * task model's method of the same name.
     *
     * @param Model $assessable
     * @return static
     */
    public function setAssessmentModel(Model $assessable);
}
