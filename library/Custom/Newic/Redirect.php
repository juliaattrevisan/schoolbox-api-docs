<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Newic;

use External\Canvas\Importer\CanvasDb;

/**
 * Redirect from schoolbox homepage to canvas somewhere
 */
class Redirect
{

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
     * Constructor.
     *
     * @param string   $canvasUrl
     * @param CanvasDb $canvasDb
     */
    public function __construct(string $canvasUrl, CanvasDb $canvasDb)
    {
        $this->canvasUrl = $canvasUrl;
        $this->canvasDb = $canvasDb;
    }


    /**
     * Redirect from schoolbox homepage to canvas somewhere
     *
     * @throws \Alaress\Database\Exception
     */
    public function redirect(string $folderCode): ?string
    {

        /* Append the current year, because that's a canvas convention apparently
         */
        $currentYear = date('Y');

        $courseCode = "{$folderCode}{$currentYear}";

        /* Find out if $folderCode exists in canvas as a course identifier
         */
        $query = "
            SELECT *
            FROM course_dim
            WHERE code = :courseCode  
        ";

        $statement = $this->canvasDb->build()->prepareQuery($query);

        $statement->execute([
            'courseCode' => $courseCode
        ]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        /* If $row is null, it didn't exist in canvas
         */
        if (null === $row) {
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
