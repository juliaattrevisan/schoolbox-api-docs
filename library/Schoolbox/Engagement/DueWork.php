<?php
/**
 * Created by PhpStorm.
 * User: jleckie
 * Date: 20/08/15
 * Time: 11:11 AM
 */

namespace Schoolbox\Engagement;
use Database;


class DueWork
{

    /**
     * Constructor - sets up database
     */
    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    public function created(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT COUNT(*) FROM submission_box
                      WHERE created >= :start AND created <= :end ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchColumn(0);
    }

    public function returned(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT COUNT(*) FROM submission_return
                      WHERE returned >= :start AND returned <= :end ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchColumn(0);
    }

    public function submitted(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT COUNT(*) FROM student_submission
                      WHERE submitted >= :start AND submitted <= :end ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchColumn(0);
    }

    public function visibility(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT mark_visibility, COUNT(*) AS total FROM submission_box
                  WHERE created >= :start AND created <= :end
                  GROUP BY mark_visibility";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchAll();

    }

}