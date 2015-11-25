<?php
/**
 * TASS implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Tass\Base;

use Config;
use MSSQL;
use Schoolbox\External\Connector\SyncInterface;
use Schoolbox\Sync\Iterator;

class Sync implements SyncInterface
{
    /**
     * @var MSSQL
     */
    private $database;

    /**
     * The TASS company code to use, for queries which require it.
     *
     * @var string
     */
    private $companyCode;

    /**
     * Sync constructor
     */
    public function __construct()
    {
        $this->database    = MSSQL::getInstance();
        $this->companyCode = Config::get('external_tass_company_code');
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $query = "
            SELECT
                s.stud_code AS StudentID,
                '' AS NetworkLogin,
                '' AS StudentTitle,
                s.preferred_name AS StudentPreferred,
                s.surname AS StudentSurname,
                s.sex AS StudentGender,
                s.e_mail AS StudentEmail,
                '' AS StudentOccupEmail,
                s.year_grp AS StudentYearLevel,
                s.house AS StudentHouse,
                (IF ((sb.resid_code IS NOT NULL), 'Y', 'N')) AS StudentBoarder,
                sb.resid_code AS StudentBoardingHouse,
                s.campus_code AS StudentCampus
            FROM student AS s
            LEFT JOIN studboard AS sb ON s.stud_code = sb.stud_code AND s.cmpy_code = sb.cmpy_code
            WHERE s.dol IS NULL
        ";
        return new Iterator\Student(
            $this->database->getIterator($query)->addMap([$this, 'trimRow'])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        // Attempt to create separate accounts for parent and spouse
        // Not yet supported: children from split families
        $query = "
            SELECT
                'P' + p.par_code AS StudentContactID,
                ''               AS StudentContactTitle,
                p.f_name         AS StudentContactPreferred,
                ''               AS StudentContactSurname,
                ''               AS StudentContactDefaultEmail,
                sf.sfa_num       AS StudentContactSplitFamilyNumber,
                s.year_grp       AS StudentYearLevel,
                s.campus_code    AS StudentCampus,
                s.sex            AS StudentGender,
                s.preferred_name AS StudentPreferred,
                s.surname        AS StudentSurname
            FROM parent AS p
            INNER JOIN paraddress as pa
                ON p.par_code = pa.par_code AND p.cmpy_code = pa.cmpy_code
            LEFT JOIN pia_sfaccess AS sf
                ON p.par_code = sf.par_code
                AND (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0)
                AND p.cmpy_code = sf.cmpy_code
            LEFT JOIN student as s
                ON (p.par_code = s.par_code AND p.cmpy_code = s.cmpy_code)
            WHERE p.f_name IS NOT NULL

            UNION ALL

            SELECT
                'S' + p.par_code AS StudentContactID,
                ''               AS StudentContactTitle,
                p.m_name         AS StudentContactPreferred,
                ''               AS StudentContactSurname,
                ''               AS StudentContactDefaultEmail,
                sf.sfa_num       AS StudentContactSplitFamilyNumber,
                s.year_grp       AS StudentYearLevel,
                s.campus_code    AS StudentCampus,
                s.sex            AS StudentGender,
                s.preferred_name AS StudentPreferred,
                s.surname        AS StudentSurname
            FROM parent AS p
            INNER JOIN paraddress as pa
                ON p.par_code = pa.par_code AND p.cmpy_code = pa.cmpy_code
            LEFT JOIN pia_sfaccess AS sf
                ON p.par_code = sf.par_code
                AND (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0)
                AND p.cmpy_code = sf.cmpy_code
            LEFT JOIN student as s
                ON (p.par_code = s.par_code AND p.cmpy_code = s.cmpy_code)
            WHERE p.m_name IS NOT NULL

            ORDER BY StudentContactID ASC
        ";

        return new Iterator\Guardian(
            $this->database->getIterator($query)
                ->addMap([$this, 'trimRow'])
                ->addMap(function ($row) {
                    list($firstName, $surname)      = preg_split("/\\s+/", $row['StudentContactPreferred'], 2);
                    $row['StudentContactPreferred'] = $firstName;
                    $row['StudentContactSurname']   = $surname;
                    return $row;
                })
                ->addMap(function ($row) {
                    if (isset($row['StudentContactSplitFamilyNumber'])) {
                        $row['StudentContactID'] .= $row['StudentContactSplitFamilyNumber'];
                    }
                    return $row;
                })
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $query = "
            SELECT
                vStaff.StaffID,
                NetworkLogin,
                ActiveFlag,
                vStaff.StaffTitle,
                vStaff.StaffPreferredName,
                vStaff.StaffSurname,
                DefaultEmailCode,
                OccupEmail,
                Email,
                vStaff.StaffCampus,
                StaffCategoryType,
                StaffCategory,
                vStaff.StaffDepartment
            FROM SynergyOne.dbo.vStaff vStaff
            LEFT JOIN Community ON Community.ID = StaffID
            WHERE ActiveFlag = 1 AND vStaff.StaffRegistrationType != 'STDNT'
            ORDER BY vStaff.StaffID ASC
        ";
        return new Iterator\Staff($this->database->getIterator($query));
    }
} 