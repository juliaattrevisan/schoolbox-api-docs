<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once('../../../../include/Bootstrap.php');

$externalClass = new External_Class();
$allStudents = $externalClass->getStudents($_POST['code']);

new \Alaress_Probe($_POST);