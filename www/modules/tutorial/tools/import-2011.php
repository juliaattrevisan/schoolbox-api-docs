<?php
/**
 * Tutorial, Class, Enrolment importer for 2011
 *
 * usage: php import-2011.php <filename>
 * or
 * http://.../tools/import-2011.php?filename=<filename>
 *
 * @author      Ryan Hughes <ryan.hughes@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once dirname(__FILE__) . '/../../../conf/conf.php';
require_once SITE_root . '/include/Database.php';
require_once SITE_root . '/library/mssql.php';

$dbh = new Database('tutorial');
$mssql = new MSSQL;

function e($string) {
    return mysql_real_escape_string($string);
}

// get top sequence
$query = "SELECT MAX(sequenceID) FROM `event` ";
$sequence = $dbh->getOne($query);

$last = array();

$filename = is_readable($argv[1]) ? $argv[1] : $_REQUEST['filename'];

// code, title, date, tutor_id, tutor_firstname, tutor_surname, student_id
$handle = fopen($filename, 'r');
while (false !== ($data = fgetcsv($handle, 1000, ','))) {

    if ($data[0] === 'Tutorial' || $data[0] === 'Title') {
        continue;
    }

    $code  = $data[0];
    $title = $data[1];
    $date = $data[2];
    $tutor_id = $data[3];
    $firstname = $data[4];
    $surname = $data[5];
    $student_id = $data[6];

    // find class
    if (!empty($code)) {
        $class = $dbh->getRow("SELECT * FROM luClass WHERE `code` = '".e($code)."'");
        if (!$class) {
            $class = array(
                'class' => $title,
                'code' => $code,
            );
            $dbh->insert('luClass', $class);
            $class['classID'] = $dbh->insertID();
        }
        $sequence++;

    }

    // find tutors portal id
    if (!empty($firstname)) {

        if (!empty($tutor_id)) {
            $tutor = $dbh->getRow("SELECT * FROM intranet.`user` WHERE `synergy_id` = '".e($tutor_id)."'");
        }

        if (!$tutor) {
            $tutor = $dbh->getRow("SELECT * FROM intranet.`user` WHERE
                `group_id` = 2 AND
                `firstname` = '".e($firstname)."' AND
                `lastname` = '".e($surname)."'");
        }

        if (!$tutor) {
            printf("Tutor not in portal db '%s %s' (%s)\n", $firstname, $surname, $tutor_id);
        }

    }


    if ($tutor) {

        // add event
        if (!empty($date)) {
            $date = date('Y-m-d', strtotime($date));
            $event = $dbh->getRow("SELECT * FROM `event` WHERE `classID` = '".e($class['classID'])."' AND `date` = DATE('".e($date)."')");
            if (!$event) {

                $event = array(
                    'typeID'     => 1,
                    'classID'    => $class['classID'],
                    'name'       => $class['class'],
                    'code'       => $class['code'],
                    'date'       => $date,
                    'start'      => '19:00:00',
                    'finish'     => '20:00:00',
                    'tutor1ID'   => $tutor['id'],
                    'sequenceID' => $sequence,
                    'created'    => 'NOW()',
                    'modified'   => 'NOW()',
                );
                $dbh->insert('event', $event);
            }
        }


        // enrol students
        if (!empty($student_id)) {
            $student = $dbh->getRow("SELECT * FROM intranet.`user` WHERE `synergy_id` = '".e($student_id)."'");
            if ($student) {
                $enrolment = $dbh->getRow("SELECT * FROM enrolment WHERE `code` = '".e($class['code'])."' AND `studentID` = '".e($student['id'])."'");
                if (!$enrolment) {
                    $enrolment = array(
                        'code' => $class['code'],
                        'studentID' => $student['id'],
                        'from' => '2011-01-01',
                    );
                    $dbh->insert('enrolment', $enrolment);
                }

            }
        }

    }


    $last = $data;
}
