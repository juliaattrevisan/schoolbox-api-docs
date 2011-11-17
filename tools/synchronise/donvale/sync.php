<?php
/**
 * sync
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

$userFile = '';
$classFile = '';
$classInvolvementFile = '';
$timetableFile = '';

$sync = new Synchronise_External_Donvale_User($userFile);
$sync->process();

$sync = new Synchronise_External_Donvale_Class($classFile);
$sync->process();

$sync = new Synchronise_External_Donvale_ClassInvolvement($classInvolvementFile);
$sync->process();

$sync = new Synchronise_External_Donvale_Timetable($timetableFile);
$sync->process();