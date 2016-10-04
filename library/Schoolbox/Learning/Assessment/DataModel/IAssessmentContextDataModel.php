<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Interface for data model representations of an instance of `\Schoolbox\Learning\AssessmentContext`.
 */
interface IAssessmentContextDataModel
{
//    /**
//     * Retrieves the folder that any Assessable objects within this object
//     * belongs within.
//     *
//     * @return FolderModel
//     */
//    public function getFolder();

    /**
     * Assessment context data models have an ID at least.
     *
     * @return int
     */
    public function getId();
}
