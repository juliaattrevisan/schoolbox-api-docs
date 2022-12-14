<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Usage\Graph\GlobalUsers;

use Alaress\Database\Connector\IConnector;

/**
 * Class UserPayload
 *
 * Calculate each user's storage usage
 *
 * @package Schoolbox\Admin\Usage\Graph\GlobalUsers
 */
class UserPayload
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
     * Get a list of users and their total usage in four specific areas
     *
     * File
     * MyFile
     * Student Submissions
     * Submission Returns
     *
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function generate(): array
    {
        $users = $this->getUsers();
        $stack = [];
        /**
         * most ineffective way of collating the data
         */
        //file
        $file = $this->getFile();
        while ($row = $file->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($users[$row['userId']]['role'])) {
                /**
                 * The original query was just joining to uses that did exist.
                 * I guess we do the same here...
                */
                continue;
            }
            //if the user name does not exist, create it
            if (!isset($stack[$row['userId']])) {
                $stack[$row['userId']] = [
                    'userId' => $users[$row['userId']]['userId'] ?? null,
                    'firstname' => $users[$row['userId']]['firstname'] ?? null,
                    'lastname' => $users[$row['userId']]['lastname'] ?? null,
                    'fullname' => $users[$row['userId']]['fullname'] ?? null,
                    'role' => $users[$row['userId']]['role'] ?? null,
                    'label' => $row['userId'],
                    'size'  => 0
                ];
            }
            //increment the group size by the row value
            $stack[$row['userId']]['size'] += $row['size'];
        }
        //myFile
        $myFile = $this->getMyFile();
        while ($row = $myFile->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($users[$row['userId']]['role'])) {
                /**
                 * The original query was just joining to uses that did exist.
                 * I guess we do the same here...
                */
                continue;
            }
            //if the user name does not exist, create it
            if (!isset($stack[$row['userId']])) {
                $stack[$row['userId']] = [
                    'userId' => $users[$row['userId']]['userId'] ?? null,
                    'firstname' => $users[$row['userId']]['firstname'] ?? null,
                    'lastname' => $users[$row['userId']]['lastname'] ?? null,
                    'fullname' => $users[$row['userId']]['fullname'] ?? null,
                    'role' => $users[$row['userId']]['role'] ?? null,
                    'label' => $row['userId'],
                    'size'  => 0
                ];
            }
            //increment the group size by the row value
            $stack[$row['userId']]['size'] += $row['size'];
        }
        //Student Submission
        $studentSubmission = $this->getStudentSubmission();
        while ($row = $studentSubmission->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($users[$row['userId']]['role'])) {
                /**
                 * The original query was just joining to uses that did exist.
                 * I guess we do the same here...
                */
                continue;
            }
            //if the user name does not exist, create it
            if (!isset($stack[$row['userId']])) {
                $stack[$row['userId']] = [
                    'userId' => $users[$row['userId']]['userId'] ?? null,
                    'firstname' => $users[$row['userId']]['firstname'] ?? null,
                    'lastname' => $users[$row['userId']]['lastname'] ?? null,
                    'fullname' => $users[$row['userId']]['fullname'] ?? null,
                    'role' => $users[$row['userId']]['role'] ?? null,
                    'label' => $row['userId'],
                    'size'  => 0
                ];
            }
            //increment the group size by the row value
            $stack[$row['userId']]['size'] += $row['size'];
        }
        //Submission Return
        $submissionReturn = $this->getSubmissionReturn();
        while ($row = $submissionReturn->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($users[$row['userId']]['role'])) {
                /**
                 * The original query was just joining to uses that did exist.
                 * I guess we do the same here...
                */
                continue;
            }
            //if the user name does not exist, create it
            if (!isset($stack[$row['userId']])) {
                $stack[$row['userId']] = [
                    'userId' => $users[$row['userId']]['userId'] ?? null,
                    'firstname' => $users[$row['userId']]['firstname'] ?? null,
                    'lastname' => $users[$row['userId']]['lastname'] ?? null,
                    'fullname' => $users[$row['userId']]['fullname'] ?? null,
                    'role' => $users[$row['userId']]['role'] ?? null,
                    'label' => $row['userId'],
                    'size'  => 0
                ];
            }
            //increment the group size by the row value
            $stack[$row['userId']]['size'] += $row['size'];
        }

        //sort by size
        usort($stack, function ($a, $b) {
            return $a['size'] <=> $b['size'];
        });
        //remove the helper labels
        return array_values($stack);
    }

    public function oldQuery(): array
    {
        $query = "
            SELECT
              `user`.id AS userId, `user`.firstname, `user`.lastname,
              CONCAT(`user`.firstname, ' ', `user`.lastname) AS fullname,
              `groups`.name AS role,
              COALESCE(SUM(size), 0) AS size
            FROM `user`
              INNER JOIN `groups` ON `user`.group_id = `groups`.id
              LEFT JOIN
              (
                (SELECT owner AS userId, SUM(`s`.`filesize`) AS size
                 FROM `file` `f`
                   INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
                 WHERE `f`.`deleted_at` IS NULL
                 GROUP BY `f`.`owner`)
                UNION
                (SELECT owner AS userId, SUM(`s`.`filesize`) AS size
                 FROM `myFile` `f`
                   INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
                 WHERE `f`.`deleted_at` IS NULL
                 GROUP BY `f`.`owner`)
                UNION
                (SELECT owner AS userId, SUM(`s`.`filesize`) AS size
                 FROM `student_submission` `f`
                   INNER JOIN `student_submission_file` ON `student_submission_file`.student_submission_id = `f`.id
                   INNER JOIN `storage` `s` ON `student_submission_file`.`hash` = `s`.`hash`
                   WHERE `student_submission_file`.deleted_at IS NULL
                 GROUP BY `f`.`owner`)
                UNION
                (SELECT owner AS userId, SUM(`s`.`filesize`) AS size
                 FROM `submission_return` `f`
                   INNER JOIN `submission_return_file` ON `submission_return_file`.submission_return_id = `f`.id
                   INNER JOIN `storage` `s` ON `submission_return_file`.`hash` = `s`.`hash`
                 WHERE `submission_return_file`.`deleted_at` IS NULL
                 GROUP BY `f`.`owner`)
              ) allTotals ON `user`.id = allTotals.userId
            GROUP BY `user`.id
            ORDER BY size
        ";

        /* Spit it out
         */
        $result = $this->database->executeQuery($query);

        $stack = [];

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $stack[] = $row;
        }

        return $stack;
    }
    private function getFile()
    {
        $query = "SELECT owner AS userId, SUM(`s`.`filesize`) AS size
                 FROM `file` `f`
                   INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
                 WHERE `f`.`deleted_at` IS NULL
                 GROUP BY `f`.`owner`";

        return $this->database->executeQuery($query);
    }

    private function getMyFile()
    {
        $query = "SELECT owner AS userId, SUM(`s`.`filesize`) AS size
                 FROM `myFile` `f`
                   INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
                 WHERE `f`.`deleted_at` IS NULL
                 GROUP BY `f`.`owner`";

        return $this->database->executeQuery($query);
    }

    private function getStudentSubmission()
    {
        $query = "SELECT owner AS userId, SUM(`s`.`filesize`) AS size
                 FROM `student_submission` `f`
                   INNER JOIN `student_submission_file` ON `student_submission_file`.student_submission_id = `f`.id
                   INNER JOIN `storage` `s` ON `student_submission_file`.`hash` = `s`.`hash`
                   WHERE `student_submission_file`.deleted_at IS NULL
                 GROUP BY `f`.`owner`";

        return $this->database->executeQuery($query);
    }

    private function getSubmissionReturn()
    {
        $query = "SELECT owner AS userId, SUM(`s`.`filesize`) AS size
                 FROM `submission_return` `f`
                   INNER JOIN `submission_return_file` ON `submission_return_file`.submission_return_id = `f`.id
                   INNER JOIN `storage` `s` ON `submission_return_file`.`hash` = `s`.`hash`
                 WHERE `submission_return_file`.`deleted_at` IS NULL
                 GROUP BY `f`.`owner`";

        return $this->database->executeQuery($query);
    }

    /**
     * get all users, for caching purposes, just get them all
     * @return array
     */
    private function getUsers()
    {
        $stack = [];
        $query = "SELECT
        `user`.id AS userId, `user`.firstname, `user`.lastname,
        CONCAT(`user`.firstname, ' ', `user`.lastname) AS fullname,
        `groups`.name AS role
        FROM user
        INNER JOIN `groups` ON `user`.group_id = `groups`.id";

        $result = $this->database->executeQuery($query);
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $stack[$row['userId']] = $row;
        }
        return $stack;
    }
}
