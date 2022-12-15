<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Usage\Graph\GlobalUsers;

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
 *
 * @package Schoolbox\Admin\Usage\Graph\GlobalUsers
 */
class SystemPayload
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
     *
     * Note that some tables such as task_files can have multiple occurrences
     * of a file hash (ie. a file gets uploaded multiple times with different names)
     */
    public function generate(): array
    {
        $stack = [
            $this->getCMSResources(),
            $this->getStudentSubmissions(),
            $this->getSubmissionReturns(),
            $this->getTasks(),
            $this->getDueWork(),
            $this->getNews(),
            $this->getPastoral(),
            $this->getMyFiles(),
            $this->getTiles(),
        ];
        return $stack;
    }

    /**
     * Calculate the size of images used in the Tiles component
     * @return array|mixed
     */
    private function getTiles()
    {
        $query = "SELECT
                'Tiles' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM storage
            WHERE hash in (SELECT background_image_hash AS hash FROM tile)";

        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }

    /**
     * Calculate the size of files within MyFiles
     * @return array|mixed
     */
    private function getMyFiles()
    {
        $query = "SELECT
                'My Files' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM storage
            WHERE hash in (SELECT hash FROM myFile)";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }

    /**
     * Calculate the size of files attached to Pastoral items
     * @return array|mixed
     */
    private function getPastoral()
    {
        $query = "SELECT
                'Pastoral' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM storage
            WHERE hash IN (SELECT hash FROM pastoral_attachment)";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }

    /**
     * Calculate the size of attachments and feature images within News
     * @return array|mixed
     */
    private function getNews()
    {
        $query = "SELECT
                'News' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (
                SELECT DISTINCT subquery.hash
                FROM
                    ((SELECT DISTINCT feature_image_hash AS hash
                    FROM comms_news_article
                    WHERE feature_image_hash IS NOT NULL)
                    UNION
                    (SELECT DISTINCT storage_id AS hash
                    FROM comms_news_article_file
                    WHERE storage_id IS NOT NULL)) subquery
            ) f
            INNER JOIN storage ON f.hash = storage.hash";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }

    /**
     * Calculate the size of files within Due Work
     * @return array|mixed
     */
    private function getDueWork()
    {
        $query = "SELECT
                'Due Work' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM storage
            WHERE hash IN (SELECT storage_id FROM submission_box_file)";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }

    /**
     * Calculate the size of files within Tasks
     * @return array|mixed
     */
    private function getTasks()
    {
        $query = "SELECT
                'Tasks' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM storage
            WHERE hash IN (SELECT storage_id AS hash FROM task_file)";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }

    /**
     * Calculate the size of files within Due Work (Submission Response)
     * @return array|mixed
     */
    private function getSubmissionReturns()
    {
        $query = "SELECT
                'Submission Returns' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM storage
            WHERE hash in (SELECT hash FROM submission_return_file)";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }

    /**
     * Calculate the size of files within Due Work (Student Submissions)
     * @return array|mixed
     */
    private function getStudentSubmissions()
    {
        $query = "SELECT
                'Student Submissions' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM storage
            WHERE hash IN (SELECT hash FROM student_submission_file)";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }

    /**
     * Calculate the size of files within the Resource Browser (CMS)
     * @return array|mixed
     */
    private function getCMSResources()
    {
        $query = "SELECT
                'CMS Resources' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM storage
            WHERE hash IN (SELECT hash FROM file)";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }
}
