<?php

/**
 * Created by PhpStorm.
 * User: jleckie
 * Date: 20/08/15
 * Time: 2:51 PM
 */

namespace Schoolbox\Engagement;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Database\Factory;

class Poll
{
    /**
     * @var IConnector
     */
    private IConnector $database;

    /**
     * Constructor - sets up database
     */
    public function __construct(IConnector $database)
    {
        $this->database = $database;
    }

    public function votes(\Alaress_Date_Format $start, \Alaress_Date_Format $end)
    {
        $query = "SELECT COUNT(*) FROM pollLog
                  WHERE created >= :start AND created <= :end";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchColumn(0);
    }
}
