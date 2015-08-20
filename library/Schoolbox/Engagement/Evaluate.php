<?php
/**
 * Created by PhpStorm.
 * User: jleckie
 * Date: 20/08/15
 * Time: 2:51 PM
 */

namespace Schoolbox\Engagement;

use Database;

class Evaluate
{

    /**
     * Constructor - sets up database
     */
    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    public function created(\Alaress_Date_Format $start, \Alaress_Date_Format $end, $type) {
        $query = "
                SELECT COUNT(*) FROM assess
                WHERE created_at >= :start AND created_at <= :end
                AND availability = :type";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());
        $statement->bindParam(':type', $type);


        $statement->execute();
        return $statement->fetchColumn(0);
    }

    public function attempted(\Alaress_Date_Format $start, \Alaress_Date_Format $end, $type) {
        $query = "SELECT COUNT(*) FROM assess_attempt JOIN assess ON assess.assess_id = assess_attempt.assess_id
                  WHERE assess_attempt.start >= :start AND assess_attempt.start <= :end
                  AND availability = :type";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());
        $statement->bindParam(':type', $type);

        $statement->execute();
        return $statement->fetchColumn(0);



    }
}