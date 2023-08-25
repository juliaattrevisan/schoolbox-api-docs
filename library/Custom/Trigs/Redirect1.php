<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Trigs;

use External\Canvas\Redirect\AbstractRedirect;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Canvas redirect for Trigs
 * @deprecated
 * TODO: OLD CANVAS DATA - REMOVE THIS IN V24
 */
class Redirect1 extends AbstractRedirect
{
    /**
     * Canvas course id is <folder_code>_<parent_folder_code>
     */
    protected string $courseIdFormat = '%s_%s';

    /**
     * The column used to find canvas course in course_dim table
     */
    protected string $courseCodeColumn = "sis_source_id";


    /**
     * @param Folder $folder
     * @param Folder $parentFolder
     * @return int|null
     */
    public function getCanvasCourseId(Folder $folder, Folder $parentFolder): ?int
    {
        $courseCode = sprintf(
            $this->courseIdFormat,
            $folder->getCodeString(),
            $parentFolder->getCodeString()
        );

        /* Find out if $folderCode exists in canvas as a course identifier
         * Only care about the states "claimed" and "available", according to the cox
         */
        $query = "
            SELECT canvas_id
            FROM course_dim
            WHERE {$this->courseCodeColumn} = :courseCode
            AND workflow_state IN ('claimed', 'available')
        ";

        $statement = $this->canvasDb->getConnector()->prepareQuery($query);

        $statement->execute(['courseCode' => $courseCode]);

        $row = $statement->fetch();

        return $row ? (int)$row['canvas_id'] : null;
    }
}
