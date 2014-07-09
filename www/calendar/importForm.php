<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$user = $_SESSION['user'];
$author = $_SESSION['user'];
$groupList = array($_POST['homepageId']);
$campusList = array();
$file = $_FILES['file']['tmp_name'];

$factory = new \Calendar\Import\Factory($user, $author, $groupList, $campusList, $file);
$strategy = $factory->build();
$strategy->load();

new \Alaress_Probe($_POST);
die();