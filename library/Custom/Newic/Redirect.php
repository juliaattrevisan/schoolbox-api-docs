<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Newic;

use Alaress\IDatabase;
use External\Canvas\Importer\CanvasDb;
use External\IConnector;


/**
 * Redirect from schoolbox homepage to canvas somewhere
 * by checking the folder code against the local canvas db cache
 *
 * If there's a matching course, manufacture a url to that place in
 * canvas instance; otherwise return null
 */
class Redirect
{

    /**
     * Newington identifier
     */
    public const SITE_SCHOOL_CODE = 'newic';


    /**
     * The host url for canvas instance at this school
     * Typically found in config.canvas_url
     *
     * @var string
     */
    private string $canvasUrl;


    /**
     * A direct connection to our local canvas db cache
     *
     * @var CanvasDb
     */
    private CanvasDb $canvasDb;


    /**
     * A direct connection to our local schoolbox db
     *
     * @var IDatabase
     */
    private IDatabase $db;


    /**
     * Constructor.
     *
     * @param string     $canvasUrl
     * @param CanvasDb   $canvasDb
     * @param IDatabase  $db
     */
    public function __construct(string $canvasUrl, CanvasDb $canvasDb, IDatabase $db)
    {
        $this->canvasUrl = trim($canvasUrl);
        $this->canvasDb = $canvasDb;
        $this->db = $db;
    }


    /**
     * For all the folder codes available to $folder,
     * Try to redirect to canvas - one of the might go somewhere meaningful
     * especially if you created it directly in schoolbox expecting it to "just work"
     *
     * @param \Schoolbox\Resource\Folder\Model $folder
     * @return string|null
     * @throws \Alaress\Database\Exception
     */
    public function generateFirstAvailableRedirect(\Schoolbox\Resource\Folder\Model $folder): ?string
    {

        /* I suppose you don't generate links to canvas if you didn't specify the url
         */
        if ('' === $this->canvasUrl) {
            return null;
        }

        /* Otherwise, test each folder code and expect one of them
         * might end up in canvas somewhere
         */
        $folderCodes = $folder->getCodes();

        /* Try each of the folder codes associated. If one of them matches a place in canvas,
         * that's where we're going. There's supposed to be a 1:1 relationship but I don't...
         * I don't really trust that opinion
         */
        foreach ($folderCodes as $folderCode) {
            $redirect = $this->generateRedirect($folderCode->getCode());

            if (null !== $redirect) {
                return $redirect;
            }
        }

        /* No redirects found
         */
        return null;
    }

    /**
     * Redirect from schoolbox homepage to canvas somewhere
     *
     * @throws \Alaress\Database\Exception
     */
    private function generateRedirect(string $folderCode): ?string
    {
        /* Append the current year, because that's a canvas convention apparently
         * The original naive approach was to just go to the destination:
         *
                $courseCode = sprintf(
                    '%s%s',
                    $folderCode,
                    date('Y')
                );
         */
        $query = "
            SELECT f2.name
            FROM folder_code fc
            INNER JOIN folder f ON fc.folder_id = f.id
            INNER JOIN course_implementation ci ON ci.folder_id = f.id
            INNER JOIN course_specification cs ON ci.course_specification_id = cs.id
            INNER JOIN folder f2 ON cs.folder_id = f2.id
            WHERE fc.code = :folderCode
            GROUP BY name
        ";

        $statement = $this->db->prepare($query);

        $statement->execute([
            'folderCode' => $folderCode
        ]);

        $row = $statement->fetch();

        /* Couldn't find a course specification folder that matches $folderCode
         * Something in the join tree didn't match, returning a null set
         */
        if (!$row) {
            return null;
        }

        /* Otherwise, you get back a folder name like this:

         * 11 IB Chemistry B 2022 (canvas-course-group-11SCICIBHLB2022)
         *
         * Now you have to regex out the course code
         */
        preg_match('/canvas-course-group-([A-za-z0-9]+)/', $row['name'], $matches);

        /* Couldn't match the regex on the folder name
         */
        if (!isset($matches[1])) {
            return null;
        }

        /* Otherwise, keep digging out the course code
         */
        $courseCode = $matches[1];

        /* Find out if $folderCode exists in canvas as a course identifier
         * Only care about the states "claimed" and "available", according to the cox
         */
        $query = "
            SELECT *
            FROM course_dim
            WHERE code = :courseCode
            AND workflow_state IN ('claimed', 'available')
        ";

        $statement = $this->canvasDb->build()->prepareQuery($query);

        $statement->execute([
            'courseCode' => $courseCode
        ]);

        $row = $statement->fetch();

        /* If $row is null, it didn't exist in canvas
         */
        if (!$row) {
            return null;
        }

        /* Extract the course_dim.id value and go to that place
         */
        return sprintf(
            '%s/courses/%s',
            rtrim($this->canvasUrl, '/'),
            $row['canvas_id']
        );
    }
}
