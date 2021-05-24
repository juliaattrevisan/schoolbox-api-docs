<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Interface for any object that may contain an Assessable object.
 *
 * This exists so that the Assessable's container knows from where to get its
 * user permissions.
 *
 * @package Schoolbox\Learning
 */
interface AssessmentContext
{
    /**
     * Retrieves this assessment context's ID.
     *
     * @return int
     */
    public function getId();

    /**
     * Sets this assessment context's ID.
     *
     * @param int $id
     * @return static
     */
    public function setId($id);

    /**
     * Retrieves the title of the assessment context
     *
     * @return string
     */
    public function getTitle();

    /**
     * Retrieves the folder that any Assessable objects within this object
     * belongs within.
     *
     * @return FolderModel
     */
    public function getFolder();

    /**
     * Get the base level context for finding where you are actually located
     * This is only really provide something different for projects which have multiple parents
     *
     * @return AssessmentContext
     */
    public function getBaseContext();

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
     * @return IAssessable[]|GenericCollection<IAssessable>
     */
    public function getAssessables();

    /**
     * @param Collection|IAssessable[] $assessables
     * @return static
     */
    public function setAssessables(Collection $assessables);

    /**
     * "id" to identify context
     * @return string
     */
    public function getLocationId(): string;

    /**
     * User friendly context path
     * @return string
     */
    public function getLocationTitle(): string;
}
