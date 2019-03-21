<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   2012 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

session_start();

$ac = new AjaxChat($_SESSION['id'], $_POST);
$ac->prepare($_POST);
$ac->respond();
