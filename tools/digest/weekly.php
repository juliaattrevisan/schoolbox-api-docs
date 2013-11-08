<?php
/**
 * News digest for JPC
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Role;
use Schoolbox\RoleMapper;

// this is run from CLI, so explicitly include the bootstrap
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

//
// Start session and set current semester, aaarrrggggh
session_start();
$synUser  = new External_User;
$semester = $synUser->getCurrentSemester();

$mailer = new Digest_Mailer();
$mailer
    ->setDebug(isset($argv[1]) && $argv[1] == 'nomail')
    ->setFromTime(new DateTime('-8 days'))
    ->setToTime(new DateTime('now'))
    ->setRoleList(RoleMapper::getInstance()->findByColumn('digest_schedule', Role::DIGEST_WEEKLY))
    ->send();
