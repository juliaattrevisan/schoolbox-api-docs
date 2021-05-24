<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Interface for data model representations of an instance of `\Schoolbox\Learning\AssessmentContext`.
 */
interface IAssessmentContextDataModel
{
    /**
     * Retrieves the folder that any Assessable objects within this object
     * belongs within.
     *
     * @return FolderModel
     */
    public function getFolder();

    /**
     * Sets the folder that any Assessable objects within this object
     * belongs within.
     *
     * @param FolderModel $folder
     * @return static
     */
    public function setFolder(FolderModel $folder);

    /**
     * Assessment context data models have an ID at least.
     *
     * @return int
     */
    public function getId();

    /**
     * Is this a context for specification assessments?
     *
     * Specification assessments can be used as a template for creating
     * implementation assessments, in which students actually participate.
     *
     * @return bool
     */
    public function isSpecificationContext();

    /**
     * Is this a context for implementation assessments?
     *
     * Implementation assessments are the assessments in which students
     * actually participate.
     *
     * @return bool
     */
    public function isImplementationContext();

    /**
     * Is this context soft deleted
     * @return mixed
     */
    public function isDeleted();
}
