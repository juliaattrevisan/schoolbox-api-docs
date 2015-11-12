<?php

/**
 * Created by PhpStorm.
 * User: jleckie
 * Date: 20/08/15
 * Time: 2:51 PM
 */

namespace Schoolbox\Engagement;

use Database;

class Mobile
{

    /**
     * Constructor - sets up database
     */
    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    public function registered(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT groups.name, COUNT(*) AS total FROM mobile_app_registry
                  JOIN `user` ON mobile_app_registry.user_id = `user`.id JOIN groups ON user.group_id = groups.id
                  WHERE created_at >= :start AND created_at <= :end
                  GROUP BY group_id";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchAll();
    }
}