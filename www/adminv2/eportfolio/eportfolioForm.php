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
    error(101);
    return;
}

// action has to be set, and not empty
if(!isset($_POST['action']) || (!$action = strtolower($_POST['action']))) {
    error(2);
    return;
}

// decide what to do
switch($action) {
    case 'delete':
        // deletion requires template id
        if(!(isset($_POST['template'])) || !($templateID = $_POST['template'])) {
            error(2);
            return;
        }
        // will be deleting $templateID
        break;
    case 'rename':
        if(!(isset($_POST['template'])) || !($templateID = $_POST['template'])) {
            error(2);
            return;
        }
        // no break: going through
    case 'new':
        if(!(isset($_POST['name'])) || !($name = $_POST['name'])) {
            error(2);
            return;
        }
        // renaming $templateID to $name, or new with $name
        break;
    default:
        error(2);
        return;
}

// if we're here, all above is valid

if($action == 'delete') {
    $sql[0] = "DELETE FROM `component_instance` WHERE `module` = 'eportfolio_template' AND `identifier` = :templateID";
    $vars[0] = array('templateID' => $templateID);

    $sql[1] = "DELETE FROM `eportfolioTemplate` WHERE `id` = :templateID";
    $vars[1] = array('templateID' => $templateID);
}
elseif($action == 'rename') {
    $sql[0] = "UPDATE `eportfolioTemplate` SET `name` = :name WHERE `id` = :templateID";
    $vars[0] = array('templateID' => $templateID, 'name' => $name);
}
else {
    $sql[0] = "INSERT INTO `eportfolioTemplate` (`name`,`layout`,`left_column_width`,`right_column_width`) VALUES (:name,'1','69.9','29.9')";
    $vars[0] = array('name' => $name);
}

for($i = 0; $i < count($sql); $i++) {
    $stmt = Database::getInstance()->prepare($sql[$i]);
    $stmt->execute($vars[$i]);
}

$sitePage->backToTheFuture(1);