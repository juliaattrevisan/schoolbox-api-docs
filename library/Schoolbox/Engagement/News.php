<?php

/**
 * Created by PhpStorm.
 * User: jleckie
 * Date: 20/08/15
 * Time: 2:51 PM
 */

namespace Schoolbox\Engagement;

use Database;

class News
{

    /**
     * Constructor - sets up database
     */
    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    public function schoolCreated(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT COUNT(*) FROM news_items
                  WHERE item_datetime_posted >= :start AND item_datetime_posted <= :end";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchColumn(0);
    }

    public function groupCreated(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT COUNT(*) FROM subject_news
                  WHERE datetime_posted >= :start AND datetime_posted <= :end";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchColumn(0);
    }
}