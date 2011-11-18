<?php
/**
 * sync
 *
 * Import data from random external CSV source and put
 * it into database.
 *
 * Due to dependencies in input data these must be run
 * in the following order:
 *
 * Students (via User)
 * Staff (via User)
 * Class
 * ClassInvolvement
 * Timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

$studentFile = './testdata/StudDetails.csv';
$staffFile = './testdata/StaffDetails.csv';
$classFile = './testdata/ACCClasses.csv';
$classInvolvementFile = './testdata/ACCClassesStud.csv';
$timetableFile = './testdata/ACCTimeTables.csv';

/* Import students
 */
$sync = new Synchronise_External_Donvale_User($studentFile);
$sync->process();

/* Import teachers
 */
$sync = new Synchronise_External_Donvale_User($staffFile);
$sync->process();

/* Import classes
 */
$sync = new Synchronise_External_Donvale_Class($classFile);
$sync->process();

/* Import class involvement
 */
$sync = new Synchronise_External_Donvale_ClassInvolvement($classInvolvementFile);
$sync->process();

/* Import timetables
 */
$sync = new Synchronise_External_Donvale_Timetable($timetableFile);
$sync->process();