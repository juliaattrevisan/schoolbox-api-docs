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
        $query = "SELECT COUNT(*) FROM assessment JOIN submission_box ON assessment.id = submission_box.assessment_id
                      WHERE created_at >= :start AND created_at <= :end ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchColumn(0);
    }

    public function returned(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT COUNT(*) FROM submission_return
                      WHERE created_at >= :start AND created_at <= :end ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchColumn(0);
    }

    public function submitted(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT COUNT(*) FROM student_submission
                      WHERE submitted_at >= :start AND submitted_at <= :end ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchColumn(0);
    }

    public function visibility(\Alaress_Date_Format $start, \Alaress_Date_Format $end) {
        $query = "SELECT mark_visibility, COUNT(*) AS total FROM assessment JOIN submission_box ON assessment.id = submission_box.assessment_id
                  WHERE created_at >= :start AND created_at <= :end
                  GROUP BY mark_visibility";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchAll();

    }

}
