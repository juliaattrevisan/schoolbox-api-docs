<?php

/**
 * Created by PhpStorm.
 * User: jleckie
 * Date: 20/08/15
 * Time: 2:51 PM
 */

namespace Schoolbox\Engagement;

use Database;

class Login
{

    /**
     * Constructor - sets up database
     */
    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    public function loginByGroup(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT groups.name,  groups.id,  COUNT(*) AS total FROM login_log JOIN  `user` ON login_log.username = `user`.username JOIN groups ON user.group_id = groups.id
                  WHERE login=1 AND date >= :start AND date <= :end
                  GROUP BY group_id";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchAll();
    }

    public function uniqueLoginByGroup(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT groups.`name`,
                    (
                        SELECT COUNT(DISTINCT(`user`.username)) FROM `user`
                        INNER JOIN  login_log ON `user`.username = login_log.username
                        WHERE group_id = groups.id AND login=1 AND `date` >= :start AND `date` <= :end
                    ) as total
                  FROM groups
                    ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchAll();
    }

}