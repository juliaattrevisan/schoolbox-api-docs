<?php

/**
 * Created by PhpStorm.
 * User: jleckie
 * Date: 20/08/15
 * Time: 2:51 PM
 */

namespace Schoolbox\Engagement;

use Database;
use Schoolbox\Database\Factory;

class Wiki
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
        $this->database = Factory::getInstanceInternal();
    }

    public function revision(\Alaress_Date_Format $start, \Alaress_Date_Format $end)
    {
        $query = "SELECT COUNT(*) FROM wikiRevision
                  WHERE created >= :start AND created <= :end";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchColumn(0);
    }
}
