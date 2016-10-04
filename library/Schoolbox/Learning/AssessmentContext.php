<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning;

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
     * Retrieves the folder that any Assessable objects within this object
     * belongs within.
     *
     * @return FolderModel
     */
    public function getFolder();
}
