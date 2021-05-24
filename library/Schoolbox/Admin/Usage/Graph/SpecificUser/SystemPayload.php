<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Usage\Graph\SpecificUser;

use Alaress\Database\Connector\IConnector;
use Schoolbox\User;

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
     *
     * @param User $user
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function generate(User $user): array
    {
        $query = "
            SELECT
                'CMS Resources' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash 
                FROM file
                WHERE owner = :userId
            ) f   
            INNER JOIN storage ON f.hash = storage.hash
            
            UNION
            
            SELECT
                'Student Submissions' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash 
                FROM student_submission_file ssf
                INNER JOIN student_submission ss ON ssf.student_submission_id = ss.id
                WHERE ss.owner = :userId
            ) f
            INNER JOIN storage ON f.hash = storage.hash
            
            UNION
            
            SELECT
                'Submission Returns' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash 
                FROM submission_return_file srf
                INNER JOIN submission_return sr ON srf.submission_return_id = sr.id
                WHERE sr.owner = :userId
            ) f   
            INNER JOIN storage ON f.hash = storage.hash
            
            UNION
            
            SELECT
                'Tasks' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT storage_id AS hash 
                FROM task_file tf
                INNER JOIN task t ON tf.task_id = t.id
                WHERE t.user_id = :userId
            ) f   
            INNER JOIN storage ON f.hash = storage.hash
            
            UNION
    
            SELECT
                'Due Work' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT storage_id AS hash 
                FROM submission_box_file sbf
                INNER JOIN submission_box sb ON sbf.box_id = sb.id
                WHERE sb.owner = :userId
            ) f
            INNER JOIN storage ON f.hash = storage.hash
            
            UNION
            
            SELECT
                'News' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (
                SELECT DISTINCT subquery.hash 
                FROM (
                    SELECT DISTINCT feature_image_hash AS hash
                    FROM comms_news_article
                    WHERE feature_image_hash IS NOT NULL
                    AND author_id = :userId
                    
                    UNION
                    
                    SELECT DISTINCT storage_id AS hash
                    FROM comms_news_article_file cnaf
                    INNER JOIN comms_news_article cna ON cnaf.article_id = cna.id
                    WHERE storage_id IS NOT NULL
                    AND cna.author_id = :userId
                ) subquery
            ) f
            INNER JOIN storage ON f.hash = storage.hash
            
            UNION
            
            SELECT
                'Pastoral' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash 
                FROM pastoral_attachment pa
                INNER JOIN pastoral p ON pa.pastoral_id = p.id
                WHERE p.owner_id = :userId
            ) f   
            INNER JOIN storage ON f.hash = storage.hash
            
            UNION
            
            SELECT
                'My Files' AS label,
                COALESCE(SUM(storage.filesize), 0) AS size
            FROM (SELECT DISTINCT hash 
                FROM myFile
                WHERE owner = :userId
            ) f   
            INNER JOIN storage ON f.hash = storage.hash
        ";

        $result = $this->database->executeQuery($query, [
            'userId' => $user->getId()
        ]);

        $stack = [];

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $stack[] = $row;
        }

        return $stack;
    }
}
