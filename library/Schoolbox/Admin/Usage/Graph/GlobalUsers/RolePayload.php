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
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function generate(): array
    {
        $query = '
            SELECT 
                `groups`.name AS label, 
                COALESCE(SUM(size), 0) AS size
            FROM groups
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
                 FROM `myFile`
                   INNER JOIN `user` ON `user`.id = `myFile`.owner
                   INNER JOIN `groups` ON `user`.group_id = `groups`.id
                   INNER JOIN `storage` ON `myFile`.`hash` = `storage`.`hash`
                 WHERE `myFile`.`deleted_at` IS NULL
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
}
