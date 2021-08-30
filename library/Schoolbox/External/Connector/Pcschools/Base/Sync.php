<?php

/**
 * Pcschools implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Pcschools\Base;

use Alaress\Database\Connector\IConnector;
use Schoolbox\External\Connector\Pcschools\QueryHelper;
use Schoolbox\External\Connector\Pcschools\QueryHelper\IQueryHelper;
use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\ISync;
use Schoolbox\IConfigLoader;
use Schoolbox\Sync\Iterator;

class Sync extends Base implements ISync
{
    const CONTACT_TYPE_FATHER    = 'F';
    const CONTACT_TYPE_MOTHER    = 'M';
    const CONTACT_TYPE_GUARDIAN  = 'G';
    const CONTACT_TYPE_CAREGIVER = 'C';

    const CONTACT_LEVEL_FULL           = 'F';
    const CONTACT_LEVEL_NONE           = 'N';
    const CONTACT_LEVEL_NO_DEMOGRAPHIC = 'D';

    /** @var IQueryHelper */
    private $queryHelper;

    /**
     * Constructor
     *
     * @param IConfigLoader    $config
     * @param array|IConnector $databaseExternal External database connector(s)
     */
    public function __construct(IConfigLoader $config, $databaseExternal)
    {
        parent::__construct($config, $databaseExternal);

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->queryHelper = (new QueryHelper\Factory($this->databaseExternal))->spawn();
    }


    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $query = "
            SELECT
                S.STUDKEY       AS student_id,
                ID.LOGIN        AS network_login,
                ''              AS student_title,
                S.GIVEN_NAME    AS student_given_name,
                CASE
                    WHEN
                        S.KNOWN_AS_NAME != ''
                    THEN S.KNOWN_AS_NAME
                    ELSE S.GIVEN_NAME
                END             AS student_preferred,
                S.SURNAME       AS student_surname,
                S.SEX           AS student_gender,
                ''              AS student_home_email,
                S.EMAIL         AS student_default_email,
                ''              AS student_work_email,
                LTRIM(RTRIM(S.CURRENT_YEAR)) AS student_year_level,
                S.HOUSE         AS student_house,
                ''              AS student_boarder,
                ''              AS student_boarding_house,
                S.CAMPUS        AS student_campus
            FROM STUDENT S
            LEFT JOIN [IDENTITY] AS ID ON S.STUDENT# = ID.MEMBER#
            WHERE (
                S.DATE_DEPART IS NULL
                OR
                DATEDIFF(YEAR, S.DATE_DEPART, '1901') >= 0
                OR
                DATEDIFF(DAY, S.DATE_DEPART, getdate()) <= 0
            )
            ORDER BY S.STUDKEY ASC
        ";

        return new Iterator\Student(
            $this->databaseExternal->getQueryIterator($query)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        [ $query, $params ] = $this->queryHelper->getFindAllParentsQuery();

        return new Iterator\Guardian(
            $this->databaseExternal->getQueryIterator($query, $params)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $query = "
            SELECT
                T.TEACHER_CODE   AS staff_id,
                ID.LOGIN         AS network_login,
                ''               AS active_flag,
                T.SALUTATION     AS staff_title,
                T.GIVEN_NAMES    AS staff_given_name,
                CASE
                    WHEN
                        T.KNOWNAS != ''
                    THEN T.KNOWNAS
                    ELSE T.GIVEN_NAMES
                END              AS staff_preferred_name,
                T.SURNAME        AS staff_surname,
                ''               AS default_email_code,
                ''               AS home_email,
                T.EMAIL          AS default_email,
                ''               AS work_email,
                T.CAMPUS         AS staff_campus,
                T.CLASSIFICATION AS staff_category_type,
                T.STATUS         AS staff_category,
                T.HOUSE_T        AS staff_house,
                ''               AS staff_department,
                ''               AS staff_year_level,
                ''               AS staff_year_level_description,
                ''               AS staff_position_code,
                ''               AS staff_position
            FROM TEACHER T
            LEFT JOIN [IDENTITY] AS ID ON T.TEACHER# = ID.MEMBER#
            WHERE (
                T.DATE_LEFT IS NULL
                OR
                DATEDIFF(YEAR, T.DATE_LEFT, '1901') >= 0
                OR
                DATEDIFF(DAY, T.DATE_LEFT, getdate()) <= 0
            )
            ORDER BY T.TEACHER_CODE ASC
        ";

        return new Iterator\Staff(
            $this->databaseExternal->getQueryIterator($query)
        );
    }
}
