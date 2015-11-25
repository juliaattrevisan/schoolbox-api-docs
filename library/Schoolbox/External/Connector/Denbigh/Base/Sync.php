<?php
/**
 * Denbigh implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Denbigh\Base;

use Config;
use Database;
use Schoolbox\External\Connector\SyncInterface;
use Schoolbox\Sync\Iterator;

class Sync implements SyncInterface
{
    /**
     * @var Database
     */
    private $database;

    /**
     * Sync constructor
     */
    public function __construct()
    {
        $hostName     = Config::get('external_db_host');
        $databaseName = Config::get('external_db_db');
        $username     = Config::get('external_db_user');
        $password     = Config::get('external_db_passwd');
        $this->database = new Database();
        $this->database->connect(
            "mysql:host=$hostName;dbname=$databaseName",
            $username,
            $password,
            array('throw' => true)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $statement = $this->database->prepare("
            SELECT
                denbighID AS StudentID,
                username AS NetworkLogin,
                '' AS StudentTitle,
                firstname AS StudentPreferred,
                surname AS StudentSurname,
                '' AS StudentGender,
                email AS StudentEmail,
                '' AS StudentOccupEmail,
                academicYear AS StudentYearLevel,
                house AS StudentHouse,
                boardingStatus AS StudentBoarder,
                cateringHouse AS StudentBoardingHouse,
                '' AS StudentCampus
            FROM Schoolbox_Student
            ORDER BY denbighID ASC
        ");
        $statement->execute();
        return new Iterator\Student($statement);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        $statement = $this->database->prepare("
            SELECT
                par.loginID AS StudentContactNetworkLogin,
                peo.denbighID AS StudentContactID,
                peo.title AS StudentContactTitle,
                preferred_name AS StudentContactPreferred,
                peo.surname AS StudentContactSurname,
                peo.email AS StudentContactDefaultEmail,
                s.denbighID AS StudentId,
                s.academicYear AS StudentYearLevel,
                '' AS StudentCampus,
                s.gender AS StudentGender,
                s.preferred_name AS StudentPreferred,
                s.surname AS StudentSurname
            FROM Addresses adr
            LEFT JOIN Parents par ON par.familyDenbighID = adr.familyDenbighID
            LEFT JOIN People peo ON peo.denbighID = par.peopleDenbighID
            LEFT JOIN Students s ON s.familyDenbighID = par.familyDenbighID
            WHERE peo.id IS NOT NULL
            GROUP BY peo.denbighID
            ORDER BY familyDenbighID ASC, denbighID ASC
        ");
        $statement->execute();
        return new Iterator\Guardian($statement);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $statement = $this->database->prepare("
            SELECT
                p.denbighID AS StaffID,
                staffCode AS NetworkLogin,
                1 AS ActiveFlag,
                title AS StaffTitle,
                preferred_name AS StaffPreferredName,
                surname AS StaffSurname,
                '' AS DefaultEmailCode,
                '' AS OccupEmail,
                email AS Email,
                '' AS StaffCampus,
                '' AS StaffCategoryType,
                '' AS StaffCategory,
                '' AS StaffDepartment
            FROM Staff s
            LEFT JOIN People p ON s.peopleDenbighID = p.denbighID
        ");
        $statement->execute();
        return new Iterator\Staff($statement);
    }
}
