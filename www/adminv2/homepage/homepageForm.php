<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// we should not be in SitePage context for this
if(isset($this)) {
    error(2);
}

require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');
$sitePage = new SitePage();

// this is an admin function, so check user is super-awesome
if(!isset($_SESSION['su']) || (!$_SESSION['su'])) {
    $sitePage->error(101);
    return;
}

// action has to be set, and not empty
if(!isset($_POST['action']) || (!$action = strtolower($_POST['action']))) {
    $sitePage->error(2);
    return;
}

// decide what to do
switch($action) {
    case 'delete':
        // deletion requires template id
        if(!(isset($_POST['template'])) || !($templateID = $_POST['template'])) {
            $sitePage->error(2);
            return;
        }
        // will be deleting $templateID
        break;
    case 'rename':
        if(!(isset($_POST['template'])) || !($templateID = $_POST['template'])) {
            $sitePage->error(2);
            return;
        }
        // no break: going through
    case 'new':
        if(!(isset($_POST['name'])) || !($name = $_POST['name'])) {
            $sitePage->error(2);
            return;
        }
        // renaming $templateID to $name, or new with $name
        break;
    default:
        $sitePage->error(2);
        return;
}

// if we're here, all above is valid


if($action == 'delete') {
    $sql = "DELETE FROM `homepageTemplate` WHERE `id` = :templateID";
    $vars = array('templateID' => $templateID);
}
elseif($action == 'rename') {
    $sql = "UPDATE `homepageTemplate` SET `name` = :name WHERE `id` = :templateID";
    $vars = array('templateID' => $templateID, 'name' => $name);
}
else {
    $sql = "INSERT INTO `homepageTemplate` (`name`,`layout`,`left_column_width`,`right_column_width`) VALUES (:name,'1','69.9','29.9')";
    $vars = array('name' => $name);
}

$stmt = Database::getInstance()->prepare($sql);
$stmt->execute($vars);

$sitePage->backToTheFuture(1);