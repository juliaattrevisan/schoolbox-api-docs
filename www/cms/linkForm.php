<?php

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Link;
use Schoolbox\Resource\LinkMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$groupHandler = new Group();

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if (($_POST['url'] ?? null) && $_POST['description'] && (is_numeric($_POST['category']) || is_array($_POST['category']))) {
    $url = new Url($_POST['url']);
    try {
        $url->normalise();
    } catch (InvalidArgumentException $e) {
        error(\Schoolbox\Error\Mapper::ERROR_LINK_FORMAT);
    }

    if (is_numeric($_POST['linkID'])) {
        /* Modify existing link
         */
        try {
            /** @var Link $link */
            $link = LinkMapper::getInstance()->findById($_POST['linkID']);

            if ($groupHandler->getPermission($link->getFolder()->getId()) < UserAccess::ACCESS_WRITE) {
                error(\Schoolbox\Error\Mapper::ERROR_PERM_CATEGORY_WRITE);
            }
        } catch (Exception $e) {
            error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        $link->setUrl($url->getUrl());
        $link->setDescription($_POST['description']);
        $link->setComment($_POST['comment']);
        $link->setHidden($_POST['visible'] ? 0 : 1);
        $link->setTarget($_POST['target']);
        $link->setModified(Alaress_Date_Format::now());

        // If the owner has been deleted - set new owner to the editor
        $owner = $link->getOwner();
        if (!$owner || null === $owner->getId() || $owner->isDeleted()) {
            $link->setOwner($sessionUser->getId());
        }
    } else {
        /** @var Link $link */
        $link = LinkMapper::getInstance()->createModel();
        $link->setOwner($sessionUser->getId());

        $link->setUrl($url->getUrl());
        $link->setDescription($_POST['description']);
        $link->setComment($_POST['comment']);
        $link->setHidden($_POST['visible'] ? 0 : 1);
        $link->setTarget($_POST['target']);

        // Add to multiple locations
        if (is_array($_POST['category'])) {
            $linkCollection = LinkMapper::getInstance()->createCollection();
            foreach ($_POST['category'] as $cat) {
                if ($groupHandler->getPermission($cat) < UserAccess::ACCESS_WRITE) {
                    error(\Schoolbox\Error\Mapper::ERROR_PERM_CATEGORY_WRITE);
                }

                $linkDest = clone $link;
                $linkDest->setFolder(FolderMapper::getInstance()->createModel($cat));
                $linkCollection->add($linkDest);
            }
            $link = $linkCollection;
        } else {
            if ($groupHandler->getPermission($_POST['category']) < UserAccess::ACCESS_WRITE) {
                error(\Schoolbox\Error\Mapper::ERROR_PERM_CATEGORY_WRITE);
            }

            $link->setFolder(FolderMapper::getInstance()->createModel($_POST['category']));
        }
    }


    LinkMapper::getInstance()->save($link);

    $page->backToTheFuture(2);
} else {
    if (is_numeric($_POST['move'] ?? null) && is_numeric($_POST['categoryID'])) {
        /* Move link from one category to another, after checking permission on both from and to categories
         */
        try {
            $link = LinkMapper::getInstance()->findById($_POST['move']);

            if ($groupHandler->getPermission($link->getFolder()->getId()) < UserAccess::ACCESS_WRITE) {
                $result = "Insufficient permssions";
            } elseif ($groupHandler->getPermission($_POST['categoryID']) < UserAccess::ACCESS_WRITE) {
                $result = "Insufficient permssions";
            } else {
                $link->setFolder(\Schoolbox\Resource\Folder\Mapper::getInstance()->createModel($_POST['categoryID']));
                $link->setModified(Alaress_Date_Format::now());
                LinkMapper::getInstance()->save($link);
                $result = "success";
            }
        } catch (Exception $e) {
            $result = "Link not found";
        }

        header("Content-type: application/json");
        print json_encode(
            array(
                'result' => $result
            )
        );
    } elseif ($_GET['delete']) {
        try {
            $link = LinkMapper::getInstance()->findById($_GET['delete']);

            if ($groupHandler->getPermission($link->getFolder()->getId()) < UserAccess::ACCESS_WRITE) {
                error(\Schoolbox\Error\Mapper::ERROR_PERM_CATEGORY_WRITE);
            }

            LinkMapper::getInstance()->delete($link);
        } catch (Exception $e) {
            error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        if (isset($_REQUEST['ajax'])) {
            die(json_encode(['result' => 'success']));
        } elseif ($_GET['redirect'] == '') {
            $page->backToTheFuture(1);
        } else {
            SitePage::localRedir($_GET['redirect']);
        }
    } else {
        error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
    }
}
