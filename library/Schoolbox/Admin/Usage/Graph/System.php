<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Alaress\Database\Connector\IConnector;


/**
 * Class System
 *
 * Calculates the system usage and puts it in a graph.
 * System Usage constitutes bytes stored across:
 *
 * CMS Resources
 * Student Submissions
 * Submissino Returns
 * Tasks
 * News
 * Pastoral
 * My Files
 * Tiles
 */
class System
{

    /**
     * @var IConnector
     */
    private IConnector $database;


    /**
     * System constructor.
     *
     * @param IConnector $database
     */
    public function __construct(IConnector $database)
    {
        $this->database = $database;
    }

    /**
     * Generate the result set for file usage
     * in each of the areas (label, quantity)
     */
    public function generate()
    {
        $query = "
            SELECT
                'CMS Resources' AS label, 
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM `file` f
            INNER JOIN storage ON f.hash = storage.hash
            
            UNION
            
            SELECT
                'Student Submissions' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM student_submission_file f
            INNER JOIN storage ON f.hash = storage.hash
            
            UNION
            
            SELECT 
                'Submission Returns' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM submission_return_file f
            INNER JOIN storage ON f.hash = storage.hash
        ";
    }
}