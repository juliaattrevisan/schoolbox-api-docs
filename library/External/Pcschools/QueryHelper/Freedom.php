<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace External\Pcschools\QueryHelper;

class Freedom implements IQueryHelper
{
    private const CONTACT_LEVEL_FULL = 'I';
    private const CONTACT_LEVEL_RESTRICTED = 'A';
    private const CONTACT_LEVEL_NONE = '';

    private const RELATION_CHILD = 'C';

    private const FAKE_RECORD_NAME = 'Second Email';


    /**
     * @return array
     */
    public function getFindAllParentsQuery(): array
    {
        $sql = "
            SELECT
            ''                                  AS student_contact_network_login,
            CONVERT(varchar(10), I.MEMBER#)     AS student_contact_id,
            ''                                  AS student_contact_title,
            CONVERT(varchar(20), I.GIVENNAME)   AS student_contact_given_name,
            CONVERT(varchar(20), I.GIVENNAME)   AS student_contact_preferred,
            CONVERT(varchar(20), I.SURNAME)     AS student_contact_surname,
            I.EMAIL                             AS student_contact_work_email,
            I.EMAIL_PERSONAL                    AS student_contact_home_email,
            S.STUDKEY                           AS student_id,
            S.CURRENT_YEAR                      AS student_year_level,
            S.CAMPUS                            AS student_campus,
            S.HOUSE                             AS student_house,
            S.SEX                               AS student_gender,
            S.GIVEN_NAME                        AS student_preferred,
            S.LEGAL_SURNAME                     AS student_surname
            FROM dbo.[IDENTITY] AS I
            INNER JOIN dbo.ALUMREL AS A ON A.PARENT# = I.MEMBER#
            INNER JOIN dbo.STUDENT AS S ON S.STUDENT# = A.CHILD#
            WHERE (A.RELATION_STAT = :relationStatus)
            AND (S.CURRENT_YEAR BETWEEN ' 5' AND '12')
            AND (A.PARENT# <> A.FAMILY_HASH) and (i.DECEASED = ' ')
            AND (A.STUDENT_PREF = :contactLevel)
            AND (givenname <> :fakeRecordName)
            ORDER BY I.MEMBER# ASC;
        ";

        $params = [
            'relationStatus' => self::RELATION_CHILD,
            'contactLevel'   => self::CONTACT_LEVEL_FULL,
            'fakeRecordName' => self::FAKE_RECORD_NAME
        ];

        return [ $sql, $params ];
    }

}