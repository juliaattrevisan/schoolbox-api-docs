<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Redirect;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception as DatabaseException;
use External\Canvas\Importer\CanvasDb;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Redirect from schoolbox homepage to canvas course page
 * by checking the folder code against the local canvas db cache
 *
 * If there's a matching course, manufacture a url to that place in
 * canvas instance; otherwise return null
 */
class AbstractRedirect
{
    /**
     * Canvas course id is {folder_code}{parent_folder_code}
     *     options are:
     * {folderCode} $folder->getCodeString()
     * {parentCode} $parentFolder->getCodeString()
     */
    protected string $courseIdFormat = '{folderCode}{parentCode}';

    /**
     * The column used to find canvas course in courses table
     */
    protected string $courseCodeColumn = 'course_code';


    protected const SIS_SOURCE_ID_COURSE_TABLE = 'courses';
    protected const SIS_SOURCE_ID_COURSE_SELECTIONS_TABLE = 'course_selections';

    /**
     * do we redirect to courses or course selections?
     * course_id, section_id
     */
    protected string $redirectDestination = 'course_id';
    /**
     * table where we map the sis_source_id to get the canvas course id
     * courses or course_selections
     */
    protected string $courseCodeTable = self::SIS_SOURCE_ID_COURSE_TABLE;

    /**
     * The host url for canvas instance at this school
     * Typically found in config.canvas_url
     *
     * @var string
     */
    protected string $canvasUrl;

    /**
     * A direct connection to our local canvas db cache
     *
     * @var CanvasDb
     */
    protected CanvasDb $canvasDb;

    /**
     * @var FolderMapper
     */
    protected FolderMapper $folderMapper;

    /**
     * A direct connection to our local schoolbox db
     *
     * @var IConnector
     */
    protected IConnector $db;

    /**
     * Constructor.
     *
     * @param string       $canvasUrl
     * @param CanvasDb     $canvasDb
     * @param FolderMapper $folderMapper
     */
    public function __construct(string $canvasUrl, CanvasDb $canvasDb, FolderMapper $folderMapper)
    {
        $this->canvasUrl    = trim($canvasUrl);
        $this->canvasDb     = $canvasDb;
        $this->folderMapper = $folderMapper;
        $this->db           = $folderMapper->getDatabase();
        if (!$canvasDb->isCanvasData2) {
            $this->courseCodeColumn = 'code';
        }
    }

    /**
     * Redirect from schoolbox homepage to canvas somewhere
     *
     * Try to redirect to canvas - assume the parent folder contains the Year as its folder code
     *
     * @param Folder $folder
     * @return string|null
     * @throws DatabaseException if the Canvas database or tables don't exist
     */
    public function generateRedirect(Folder $folder): ?string
    {
        /* I suppose you don't generate links to canvas if you didn't specify the url
         */
        if ('' === $this->canvasUrl) {
            return null;
        }

        /* Assume the parent of $folder is the year node
         * e.g. "2022 (2022)"
         * And get the Course Code using that folder's folder code
         *
         * If you don't have a parent folder, just return null
         * That isn't supposed to happen
         */
        if (null === $folder->getParent()) {
            return null;
        }

        /* There are some big assumptions about this folder tree, but I can't really
         * move the pieces around in andy's importer without incurring a penalty
         *
         * Just blindly assume the parent a) exists b) works c) has a course code
         */
        try {
            $parentFolder = $this->folderMapper->findById($folder->getParent()->getId());
        } catch (MapperException) {
            return null;
        }

        $canvasCourseId = $this->getCanvasCourseId($folder, $parentFolder);

        /* If $row is null, it didn't exist in canvas
         */
        if (!$canvasCourseId) {
            return null;
        }

        /* Extract the course_dim.id value and go to that place
         */
        return sprintf(
            '%s/courses/%s',
            rtrim($this->canvasUrl, '/'),
            $canvasCourseId
        );
    }

    /**
     * @param Folder $folder
     * @param Folder $parentFolder
     * @return int|null
     * @throws DatabaseException if the Canvas database or tables don't exist
     */
    public function getCanvasCourseId(Folder $folder, Folder $parentFolder): ?int
    {
        $courseCode = strtr(
            $this->courseIdFormat, // now formattable string
            [
                '{folderCode}' => $folder->getCodeString(),
                '{parentCode}' => $parentFolder->getCodeString()
            ]
        );

        if ($this->courseCodeTable === self::SIS_SOURCE_ID_COURSE_TABLE) {
            /* Find out if $folderCode exists in canvas as a course identifier
             * Only care about the states "claimed" and "available", according to the cox
             */
            $query = "
            SELECT id as course_id
            FROM courses
            WHERE {$this->courseCodeColumn} = :courseCode
            AND workflow_state IN ('claimed', 'available')";

            if (!$this->canvasDb->isCanvasData2) {
                $query = "
            SELECT course_id
            FROM course_dim
            WHERE {$this->courseCodeColumn} = :courseCode
            AND workflow_state IN ('claimed', 'available')";
            }
        } else {
            $query = "
            SELECT c.id as course_id, cs.id as section_id, cs.sis_source_id, term.name as term_name, term.sis_source_id as term_sis_id
             FROM courses c
             JOIN course_sections cs ON cs.course_id = c.id
             JOIN enrollment_terms term ON term.id = c.enrollment_term_id
             WHERE
                 cs.{$this->courseCodeColumn} = :courseCode
             AND c.workflow_state in ('available', 'completed');
            ";
        }

        $statement = $this->canvasDb->getConnector()->prepareQuery($query);

        $statement->execute(['courseCode' => $courseCode]);

        $row = $statement->fetch();

        return $row ? (int)$row[$this->redirectDestination] : null;
    }
}
