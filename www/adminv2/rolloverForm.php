<?php

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\HomepageConfig;

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// disallow SitePage context
if (isset($this)) {
    /** @var $this SitePage */
    $this->localRedir('/adminv2/rollover');
    exit;
}

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

header('Content-Type: application/json');

if (
    !$_SESSION['user']->isSuperuser() &&
    !$_SESSION['user']->hasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL)
) {
    respond("You are not authorised to perform that function.", 403);
}


// what are we doing?

if (count($_POST) != 1) {
    respond('Bad request', 400);
}

// return a traversal
if (isset($_POST['traverse'])) {
    $cats = traverse($_POST['traverse']);
    respond($cats);
}

// perform a rollover
if (isset($_POST['rollover'])) {
    rollover($_POST['rollover']);
}


/**
 * Recursive function that retrieves the children of a category that have class codes attached.
 *
 * @param $categoryId
 * @param array $catsWithCodes
 * @return array
 */
function traverse($categoryId, $catsWithCodes = array())
{
    $folderMapper = new \Schoolbox\Resource\Folder\Mapper();
    $allFolders = $folderMapper->findByParent($categoryId);

    /* For JSON ajax
     */
    foreach ($allFolders as $currentFolder) {
        if (strlen($currentFolder->getCodeString())) {
            $homepage = $currentFolder->getHomepage();
            $catsWithCodes[$currentFolder->getId()] = array(
                'id' => $currentFolder->getId(),
                'name' => $currentFolder->getName(),
                'parent' => $currentFolder->getParent()->getId(),
                'template' => $homepage instanceof HomepageConfig ? $homepage->getTemplate()->getId() : null,
                'code' => $currentFolder->getCodeString()
            );
        }

        $catsWithCodes = traverse($currentFolder->getId(), $catsWithCodes);
    }

    return $catsWithCodes;
}

/**
 * Perform a rollover. This is an idempotent function, so if the request is repeated it's ok
 *
 * @param $categoryId
 */
function rollover($categoryId)
{

    // send the user back to the rollover page, and then process the rollover request
    SitePage::localRedir('/adminv2/rollover', true);

    // post-client processing
    $cats = traverse($categoryId);

    $catMapper = new \Schoolbox\Resource\Folder\Mapper();
    $codeMapper = new \Schoolbox\Resource\Folder\Code\Mapper();

    // for each category retrieved: rename to ":categoryName [:code/s]"; remove class relation
    foreach ($cats as $cat) {
        $cat = $catMapper->findById($cat['id']);
        $cat->setName(sprintf('%s [%s]', $cat->getName(), $cat->getCodeString()));
        $codeMapper->delete($cat->getCodes());
        $cat->setCodes($codeMapper->createCollection());
        $catMapper->save($cat);
    }

    exit;
}


function respond($content, $code = 200)
{
    http_response_code($code);
    die(json_encode($content));
}
