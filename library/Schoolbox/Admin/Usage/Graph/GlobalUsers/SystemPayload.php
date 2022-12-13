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

    private function oldMethod()
    {
        $query = "
            SELECT
                'CMS Resources' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash FROM file) f
            INNER JOIN storage ON f.hash = storage.hash

            UNION

            SELECT
                'Student Submissions' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash FROM student_submission_file) f
            INNER JOIN storage ON f.hash = storage.hash

            UNION

            SELECT
                'Submission Returns' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash FROM submission_return_file) f
            INNER JOIN storage ON f.hash = storage.hash

            UNION

            SELECT
                'Tasks' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT storage_id AS hash FROM task_file) f
            INNER JOIN storage ON f.hash = storage.hash

            UNION

            SELECT
                'Due Work' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT storage_id FROM submission_box_file) f
            INNER JOIN storage ON f.storage_id = storage.hash

            UNION

            SELECT
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
            INNER JOIN storage ON f.hash = storage.hash

            UNION

            SELECT
                'Pastoral' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash FROM pastoral_attachment) f
            INNER JOIN storage ON f.hash = storage.hash

            UNION

            SELECT
                'My Files' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash FROM myFile) f
            INNER JOIN storage ON f.hash = storage.hash

            UNION

            SELECT
                'Tiles' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT background_image_hash AS hash FROM tile) f
            INNER JOIN storage ON f.hash = storage.hash
        ";

        $result = $this->database->executeQuery($query);

        $stack = [];

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $stack[] = $row;
        }

        return $stack;
    }

    private function getTiles()
    {
        $query = "SELECT
                'Tiles' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT background_image_hash AS hash FROM tile) f
            INNER JOIN storage ON f.hash = storage.hash";

        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }

    private function getMyFiles()
    {
        $query = "SELECT
                'My Files' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash FROM myFile) f
            INNER JOIN storage ON f.hash = storage.hash";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }
    private function getPastoral()
    {
        $query = "SELECT
                'Pastoral' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash FROM pastoral_attachment) f
            INNER JOIN storage ON f.hash = storage.hash";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }

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

    private function getDueWork()
    {
        $query = "SELECT
                'Due Work' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT storage_id FROM submission_box_file) f
            INNER JOIN storage ON f.storage_id = storage.hash";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }

    private function getTasks()
    {
        $query = "SELECT
                'Tasks' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT storage_id AS hash FROM task_file) f
            INNER JOIN storage ON f.hash = storage.hash";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }

    private function getSubmissionReturns()
    {
        $query = "SELECT
                'Submission Returns' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash FROM submission_return_file) f
            INNER JOIN storage ON f.hash = storage.hash";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }

    private function getStudentSubmissions()
    {
        $query = "SELECT
                'Student Submissions' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash FROM student_submission_file) f
            INNER JOIN storage ON f.hash = storage.hash";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }

    private function getCMSResources()
    {
        $query = "SELECT
                'CMS Resources' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash FROM file) f
            INNER JOIN storage ON f.hash = storage.hash";
        $result = $this->database->executeQuery($query);
        return $result->fetch();
    }
}
