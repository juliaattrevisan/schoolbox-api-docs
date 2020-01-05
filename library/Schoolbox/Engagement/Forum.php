<?php

/**
 * Created by PhpStorm.
 * User: jleckie
 * Date: 20/08/15
 * Time: 2:51 PM
 */

namespace Schoolbox\Engagement;

use Database;

class Forum
{
    /**
     * @var Database
     */
    private $database;

    /**
     * Constructor - sets up database
     */
    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    public function created(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT COUNT(*) FROM forumPost
                  WHERE date >= :start AND date <= :end";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchColumn(0);
    }

}
