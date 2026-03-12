<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Usage\Graph\GlobalUsers;

use Alaress\Database\Connector\IConnector;

/**
 * Class RolePayload
 *
 * Storage usage, grouped by Roles
 *
 * @package Schoolbox\Admin\Usage\Graph\GlobalUsers
 */
class RolePayload
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
     * Generate usage occupied by each of the Roles
     *
     * @return array<int, array{
     *      label: string,
     *      size: int,
     *      sequence: int
     *     }
     * >
     * @throws \Alaress\Database\Exception
     */
    public function generate(): array
    {
        $stack = [];
        /**
         * most ineffective way of collating the data
         */
        //file
        $file = $this->getFile();
        while ($row = $file->fetch(\PDO::FETCH_ASSOC)) {
            //if the role name does not exist, create it
            if (!isset($stack[$row['name']])) {
                $stack[$row['name']] = [
                    'label'     => $row['name'],
                    'size'      => 0,
                    'sequence'  => $row['sequence']
                ];
            }
            //increment the group size by the row value
            $stack[$row['name']]['size'] += $row['size'];
        }
        //myFile
        $myFile = $this->getMyFile();
        while ($row = $myFile->fetch(\PDO::FETCH_ASSOC)) {
            //if the role name does not exist, create it
            if (!isset($stack[$row['name']])) {
                $stack[$row['name']] = [
                    'label'     => $row['name'],
                    'size'      => 0,
                    'sequence'  => $row['sequence']
                ];
            }
            //increment the group size by the row value
            $stack[$row['name']]['size'] += $row['size'];
        }
        //Submission Box
        $submissionBox = $this->getSubmissionBox();
        while ($row = $submissionBox->fetch(\PDO::FETCH_ASSOC)) {
            //if the role name does not exist, create it
            if (!isset($stack[$row['name']])) {
                $stack[$row['name']] = [
                    'label'     => $row['name'],
                    'size'      => 0,
                    'sequence'  => $row['sequence']
                ];
            }
            //increment the group size by the row value
            $stack[$row['name']]['size'] += $row['size'];
        }
        //Submission Return
        $submissionReturn = $this->getSubmissionReturn();
        while ($row = $submissionReturn->fetch(\PDO::FETCH_ASSOC)) {
            //if the role name does not exist, create it
            if (!isset($stack[$row['name']])) {
                $stack[$row['name']] = [
                    'label'     => $row['name'],
                    'size'      => 0,
                    'sequence'  => $row['sequence']
                ];
            }
            //increment the group size by the row value
            $stack[$row['name']]['size'] += $row['size'];
        }
        //sort by role sequence
        usort($stack, function ($a, $b) {
            return $a['sequence'] <=> $b['sequence'];
        });
        //remove the helper labels
        return array_values($stack);
    }

    public function oldQuery(): array
    {
        $query = '
            SELECT
                `groups`.name AS label,
                COALESCE(SUM(size), 0) AS size
            FROM `groups`
              LEFT JOIN
              (
                (SELECT `groups`.id, `groups`.name, SUM(`storage`.`filesize`) AS size
                 FROM `file`
                   INNER JOIN `user` ON `user`.id = `file`.owner
                   INNER JOIN `groups` ON `user`.group_id = `groups`.id
                   INNER JOIN `storage` ON `file`.`hash` = `storage`.`hash`
                 WHERE `file`.`deleted_at` IS NULL
                 GROUP BY `groups`.id)
                UNION
                (SELECT `groups`.id, `groups`.name, SUM(`storage`.`filesize`) AS size
                 FROM my_file
                   INNER JOIN `user` ON `user`.id = my_file.owner
                   INNER JOIN `groups` ON `user`.group_id = `groups`.id
                   INNER JOIN `storage` ON my_file.`hash` = `storage`.`hash`
                 WHERE my_file.`deleted_at` IS NULL
                 GROUP BY `groups`.id)
                UNION
                (SELECT `groups`.id, `groups`.name, SUM(`storage`.`filesize`) AS size
                 FROM `submission_box`
                   INNER JOIN `user` ON `user`.id = `submission_box`.owner
                   INNER JOIN `groups` ON `user`.group_id = `groups`.id
                   INNER JOIN `submission_box_file` ON `submission_box_file`.box_id = `submission_box`.id
                   INNER JOIN `storage` ON `submission_box_file`.`storage_id` = `storage`.`storage_id`
                 GROUP BY `groups`.id)
                UNION
                (SELECT `groups`.id, `groups`.name, SUM(`storage`.`filesize`) AS size
                 FROM `submission_return`
                   INNER JOIN `user` ON `user`.id = `submission_return`.owner
                   INNER JOIN `groups` ON `user`.group_id = `groups`.id
                   INNER JOIN `submission_return_file` ON `submission_return_file`.`submission_return_id` = `submission_return`.`id`
                   INNER JOIN `storage` ON `submission_return_file`.`hash` = `storage`.`hash`
                 WHERE `submission_return`.`deleted_at` IS NULL
                 GROUP BY `groups`.id)
              ) allTotals ON `groups`.id = allTotals.id
            GROUP BY `groups`.name
            ORDER BY `groups`.sequence
        ';

        $result = $this->database->executeQuery($query);

        $stack = [];

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $stack[] = $row;
        }

        return $stack;
    }

    /**
     * Get the file table storage usage by role
     * this will ignore files that have been deleted in the file table (Resource Browser)
     * @return \Alaress\Database\Statement\Query\Base|\Alaress\Database\Statement\Query\IQuery
     */
    private function getFile()
    {
        $query = "SELECT `groups`.id, `groups`.name, `groups`.sequence, SUM(`storage`.`filesize`) AS size
                 FROM `file`
                   INNER JOIN `user` ON `user`.id = `file`.owner
                   INNER JOIN `groups` ON `user`.group_id = `groups`.id
                   INNER JOIN `storage` ON `file`.`hash` = `storage`.`hash`
                 WHERE `file`.`deleted_at` IS NULL
                 GROUP BY `groups`.id";

        return $this->database->executeQuery($query);
    }

    /**
     * Get the myFile table storage usage by role
     * this will ignore files that have been deleted in the MyFile table (My Files)
     * @return \Alaress\Database\Statement\Query\Base|\Alaress\Database\Statement\Query\IQuery
     */
    private function getMyFile()
    {
        $query = "SELECT `groups`.id, `groups`.name, `groups`.sequence, SUM(`storage`.`filesize`) AS size
                 FROM my_file
                   INNER JOIN `user` ON `user`.id = my_file.owner
                   INNER JOIN `groups` ON `user`.group_id = `groups`.id
                   INNER JOIN `storage` ON my_file.`hash` = `storage`.`hash`
                 WHERE my_file.`deleted_at` IS NULL
                 GROUP BY `groups`.id";

        return $this->database->executeQuery($query);
    }

    /**
     * Get the student_submission_file table storage usage by role
     * this will ignore files that have been deleted in the student_submission_file table (Due Work Submission)
     * @return \Alaress\Database\Statement\Query\Base|\Alaress\Database\Statement\Query\IQuery
     */
    private function getSubmissionBox()
    {
        $query = "SELECT `groups`.id, `groups`.name, `groups`.sequence, SUM(`storage`.`filesize`) AS size
                 FROM `submission_box`
                   INNER JOIN `user` ON `user`.id = `submission_box`.owner
                   INNER JOIN `groups` ON `user`.group_id = `groups`.id
                   INNER JOIN `submission_box_file` ON `submission_box_file`.box_id = `submission_box`.id
                   INNER JOIN `storage` ON `submission_box_file`.`storage_id` = `storage`.`storage_id`
                 GROUP BY `groups`.id";

        return $this->database->executeQuery($query);
    }

    /**
     * Get the submission_return_file table storage usage by role
     * this will ignore files that have been deleted in the submission_return_file table (Due Work Submission Response)
     * @return \Alaress\Database\Statement\Query\Base|\Alaress\Database\Statement\Query\IQuery
     */
    private function getSubmissionReturn()
    {
        $query = "SELECT `groups`.id, `groups`.name, `groups`.sequence, SUM(`storage`.`filesize`) AS size
                 FROM `submission_return`
                   INNER JOIN `user` ON `user`.id = `submission_return`.owner
                   INNER JOIN `groups` ON `user`.group_id = `groups`.id
                   INNER JOIN `submission_return_file` ON `submission_return_file`.`submission_return_id` = `submission_return`.`id`
                   INNER JOIN `storage` ON `submission_return_file`.`hash` = `storage`.`hash`
                 WHERE `submission_return`.`deleted_at` IS NULL
                 GROUP BY `groups`.id";

        return $this->database->executeQuery($query);
    }
}
