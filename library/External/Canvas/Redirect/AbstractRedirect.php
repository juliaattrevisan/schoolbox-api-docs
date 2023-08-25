<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Redirect;

use Alaress\Database\Connector\IConnector;
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
     * Canvas course id is <folder_code><parent_folder_code>
     */
    protected string $courseIdFormat = '%s%s';

    /**
     * The column used to find canvas course in courses table
     */
    protected string $courseCodeColumn = 'course_code';

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
    }

    /**
     * Redirect from schoolbox homepage to canvas somewhere
     *
     * Try to redirect to canvas - assume the parent folder contains the Year as its folder code
     *
     * @param Folder $folder
     * @return string|null
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
            SELECT id
            FROM courses
            WHERE {$this->courseCodeColumn} = :courseCode
            AND workflow_state IN ('claimed', 'available')
        ";

        $statement = $this->canvasDb->getConnector()->prepareQuery($query);

        $statement->execute(['courseCode' => $courseCode]);

        $row = $statement->fetch();

        return $row ? (int)$row['id'] : null;
    }
}
