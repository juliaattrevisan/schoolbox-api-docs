<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// we should not be in SitePage context for this
use Schoolbox\Database\Factory;
use Schoolbox\Error\Mapper as ErrorMapper;

if (isset($this)) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
}

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $sitePage */
$sitePage = $kernel->get('sitePage');
$sitePage->initSession();

// this is an admin function, so check user is super-awesome
if (!isset($_SESSION['su']) || (!$_SESSION['su'])) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
}

// action has to be set, and not empty
if (!isset($_POST['action']) || (!$action = strtolower($_POST['action']))) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
}

// decide what to do
switch ($action) {
    case 'delete':
        // deletion requires template id
        if (!(isset($_POST['template'])) || !($templateID = $_POST['template'])) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
        // will be deleting $templateID
        break;
    case 'rename':
        if (!(isset($_POST['template'])) || !($templateID = $_POST['template'])) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
        // no break: going through
    case 'create':
        if (!(isset($_POST['name'])) || !($name = $_POST['name'])) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
        // renaming $templateID to $name, or new with $name
        break;
    default:
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
}

// if we're here, all above is valid

if ($action == 'delete') {
    $sql[0] = "DELETE FROM `component_instance` WHERE `module` = 'eportfolio_template' AND `identifier` = :templateID";
    $vars[0] = array('templateID' => $templateID);

    $sql[1] = "DELETE FROM `eportfolioTemplate` WHERE `id` = :templateID";
    $vars[1] = array('templateID' => $templateID);
} elseif ($action == 'rename') {
    $sql[0] = "UPDATE `eportfolioTemplate` SET `name` = :name WHERE `id` = :templateID";
    $vars[0] = array('templateID' => $templateID, 'name' => $name);
} else {
    $sql[0] = "INSERT INTO `eportfolioTemplate` (`name`,`layout`,`left_column_width`,`right_column_width`) VALUES (:name,'1','69.5','29.5')";
    $vars[0] = array('name' => $name);
}

for ($i = 0; $i < count($sql); $i++) {
    $stmt = Factory::getInstanceInternal()->prepare($sql[$i]);
    $stmt->execute($vars[$i]);
}

$sitePage->backToTheFuture(1);
