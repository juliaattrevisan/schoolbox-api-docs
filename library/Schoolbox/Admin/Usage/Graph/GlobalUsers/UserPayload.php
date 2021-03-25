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
}