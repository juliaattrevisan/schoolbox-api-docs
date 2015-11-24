<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Synchronise\Synergetic\Diocesan\Hydrator;


use Synchronise_Base_Hydrator_Student;

class Student extends Synchronise_Base_Hydrator_Student
{
    const ROLE_YR_F  = 1;
    const ROLE_YR_1  = 2;
    const ROLE_YR_2  = 3;
    const ROLE_YR_3  = 4;
    const ROLE_YR_4  = 5;
    const ROLE_YR_5  = 6;
    const ROLE_YR_6  = 7;
    const ROLE_YR_7  = 8;
    const ROLE_YR_8  = 9;
    const ROLE_YR_9  = 10;
    const ROLE_YR_10 = 11;
    const ROLE_YR_11 = 12;
    const ROLE_YR_12 = 13;
    const ROLE_YR_13 = 14;

    const CAMPUS_JUNIOR  = 1;
    const CAMPUS_JUNHIGH = 2;
    const CAMPUS_SENIOR  = 3;


    /**
     * Converts data from the format from external database to the format used
     * in Schoolbox.
     *
     * This method should modify and return $newUser.
     *
     * @param  array $externalUser A result from the external database.
     * @param  array $newUser      The current result that will be inserted into
     *                             Schoolbox.
     *                             This result could have come from a hydrate()
     *                             call on another class that implements this
     *                             interface.
     * @return array The result to insert into Schoolbox,
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser = parent::hydrate($externalUser, $newUser);

        /*
         * IF externalUser.StudentYearLevel = 0
         * AND externalUser.StudentCampus = F
         * THEN role = Foundation Class (1)
         * AND defaultCampus = Junior School (1)
         *
         *
         * IF externalUser.StudentCampus NOT = F
         * THEN role MAPS externalUser.StudentYearLevel AS [
         *    0 => Year 1 Students (2)
         *    1 => Year 1 Students (2)
         *    2 => Year 2 Students (3)
         *    3 => Year 3 Students (4)
         *    4 => Year 4 Students (5)
         *    5 => Year 5 Students (6)
         *    6 => Year 6 Students (7)
         *    7 => Year 7 Students (8)
         *    8 => Year 8 Students (9)
         *    9 => Year 9 Students (10)
         *   10 => Year 10 Students (11)
         *   11 => Year 11 Students (12)
         *   12 => Year 12 Students (13)
         *   13 => Year 13 Students (14)
         * ]
         *
         *
         * IF externalUser.StudentCampus = J
         * THEN defaultCampus = Junior School (1)
         *
         *
         * IF externalUser.StudentCampus = S
         * AND externalUser.StudentYearLevel IN (7, 8, 9, 10)
         * THEN defaultCampus = Junior High (2)
         *
         *
         * IF externalUser.StudentCampus = S
         * AND externalUser.StudentYearLevel IN (11, 12, 13)
         * THEN default Campus = Senior School (3)
         *
         */
        $roleMap = array(
            'F' => array(
                0 => array(self::ROLE_YR_F, self::CAMPUS_JUNIOR),
            ),
            'J' => array(
                0 => array(self::ROLE_YR_1, self::CAMPUS_JUNIOR),
                1 => array(self::ROLE_YR_1, self::CAMPUS_JUNIOR),
                2 => array(self::ROLE_YR_2, self::CAMPUS_JUNIOR),
                3 => array(self::ROLE_YR_3, self::CAMPUS_JUNIOR),
                4 => array(self::ROLE_YR_4, self::CAMPUS_JUNIOR),
                5 => array(self::ROLE_YR_5, self::CAMPUS_JUNIOR),
                6 => array(self::ROLE_YR_6, self::CAMPUS_JUNIOR),
            ),
            'S' => array(
                7  => array(self::ROLE_YR_7, self::CAMPUS_JUNHIGH),
                8  => array(self::ROLE_YR_8, self::CAMPUS_JUNHIGH),
                9  => array(self::ROLE_YR_9, self::CAMPUS_JUNHIGH),
                10 => array(self::ROLE_YR_10, self::CAMPUS_JUNHIGH),
                11 => array(self::ROLE_YR_11, self::CAMPUS_SENIOR),
                12 => array(self::ROLE_YR_12, self::CAMPUS_SENIOR),
                13 => array(self::ROLE_YR_13, self::CAMPUS_SENIOR),
            )
        );

        $campus = $externalUser['StudentCampus'];
        $year   = $externalUser['StudentYearLevel'];

        $newUser['year'] = $year;

        if (isset($roleMap[$campus][$year])) {
            $newUser['group_id']      = $roleMap[$campus][$year][0];
            $newUser['campus']        = array($roleMap[$campus][$year][1]);
            $newUser['defaultCampus'] = $roleMap[$campus][$year][1];

        } else {
            $newUser['year'] = self::ROLE_YR_F;
        }

        return $newUser;
    }

}