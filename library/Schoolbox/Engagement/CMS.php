<?php

/**
 * Created by PhpStorm.
 * User: jleckie
 * Date: 20/08/15
 * Time: 2:51 PM
 */

namespace Schoolbox\Engagement;

use Database;

class CMS
{

    /**
     * Constructor - sets up database
     */
    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    public function log(\Alaress_Date_Format $start, \Alaress_Date_Format $end, $action, $object) {
        $query = "
                SELECT groups.name, COUNT(*) as total FROM cmsLog JOIN `user` ON cmsLog.user = `user`.id JOIN groups ON user.group_id = groups.id
                WHERE object = :object AND action = :action AND
                `time` >= :start AND `time` <= :end
                GROUP BY group_id";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());
        $statement->bindParam(':action', $action);
        $statement->bindParam(':object', $object);


        $statement->execute();
        return $statement->fetchAll();
    }

}