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
 * Class TotalPayload
 *
 * @package Schoolbox\Admin\Usage\Graph
 */
class TotalPayload
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
     * A person's specific usage consists of the following:
     *
     * Files
     * MyFiles
     * Student Submissions
     * Submission Return
     *
     * (This aggregation remained unchanged from its original implementation)
     *
     * @param User $user
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function generate(User $user): array
    {
        $query = '
            SELECT
              COALESCE(SUM(size), 0) AS size,
              COALESCE(SUM(num), 0) AS num
            FROM (
                (SELECT 
                    SUM(`s`.`filesize`) AS size, 
                    COUNT(*) AS num
                 FROM `file` `f`
                   INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
                 WHERE `f`.`owner` = :userId
                       AND `f`.`deleted_at` IS NULL
                 GROUP BY `f`.`owner`)
                 
                UNION
                
                (SELECT 
                    SUM(`s`.`filesize`) AS size, 
                    COUNT(*) AS num
                 FROM `myFile` `f`
                   INNER JOIN `storage` `s` ON `f`.`hash` = `s`.`hash`
                 WHERE `f`.`owner` = :userId
                       AND `f`.`deleted_at` IS NULL
                 GROUP BY `f`.`owner`)
                 
                UNION
                
                (SELECT 
                    SUM(`s`.`filesize`) AS size, 
                    COUNT(*) AS num
                 FROM `student_submission` `f`
                   INNER JOIN `student_submission_file` ON `student_submission_file`.student_submission_id = `f`.id
                   INNER JOIN `storage` `s` ON `student_submission_file`.`hash` = `s`.`hash`
                 WHERE `f`.`owner` = :userId
                 AND `student_submission_file`.`deleted_at` IS NULL
                 GROUP BY `f`.`owner`)
                 
                UNION
                
                (SELECT 
                    SUM(`s`.`filesize`) AS size, 
                    COUNT(*) AS num
                 FROM `submission_return` `f`
                   INNER JOIN `submission_return_file` ON `submission_return_file`.submission_return_id = `f`.id
                   INNER JOIN `storage` `s` ON `submission_return_file`.`hash` = `s`.`hash`
                 WHERE `f`.`owner` = :userId
                       AND `submission_return_file`.`deleted_at` IS NULL
                 GROUP BY `f`.`owner`)
              ) AS allTotals
        ';

        $result = $this->database->executeQuery($query, [
            'userId' => $user->getId()
        ]);

        return $result->fetch(\PDO::FETCH_ASSOC);
    }
}
