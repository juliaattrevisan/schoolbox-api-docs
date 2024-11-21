<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\UserAccess;

// we should not be in SitePage context for this
if (isset($this)) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
}

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

// this is an admin function, so check user is super-awesome
if (
    !$_SESSION['user']->isSuperuser() &&
    !$_SESSION['user']->hasServicePermission(UserAccess::SERVICE_DESIGN, UserAccess::ACCESS_FULL)
) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
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
$homepageTemplateMapper = new \Schoolbox\Resource\HomepageTemplateMapper();

if ($action == 'delete') {
    try {
        $homepageTemplate = $homepageTemplateMapper->findById($templateID);
        $homepageTemplateMapper->delete($homepageTemplate);
    } catch (Schoolbox\Mapper\Exception $e) {
    }
} elseif ($action == 'rename') {
    $homepageTemplate = $homepageTemplateMapper->findById($templateID);
    $homepageTemplate->setName($name);

    try {
        $homepageTemplateMapper->save($homepageTemplate);
    } catch (UnexpectedValueException $e) {
        throw new SchoolboxException($e->getMessage(), 0, $e);
    }
} else {
    $homepageTemplate = $homepageTemplateMapper->createModel();
    $homepageTemplate->setName($name)->setLeftColumnWidth(69.5)->setRightColumnWidth(29.5);

    try {
        $homepageTemplateMapper->create($homepageTemplate);
        // navigate to the new template
        SitePage::localRedir(sprintf("/adminv2/homepage#%d", $homepageTemplate->getId()));
    } catch (UnexpectedValueException $e) {
        throw new SchoolboxException($e->getMessage(), 0, $e);
    }
}

SitePage::backToTheFuture(1);
