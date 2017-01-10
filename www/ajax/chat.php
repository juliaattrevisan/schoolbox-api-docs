<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   2012 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

require_once dirname(__FILE__) . '/../../library/Bootstrap.php';

session_start();

$ac = new AjaxChat($_SESSION['id'], $_POST);
$ac->prepare($_POST);
$ac->respond();
