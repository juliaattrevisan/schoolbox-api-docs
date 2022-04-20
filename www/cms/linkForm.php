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

$description = $_POST['description'] ?? null;
$category    = $_POST['category'] ?? null;
$linkUrl     = $_POST['url'] ?? null;
if ($linkUrl && $description && (is_numeric($category) || is_array($category))) {
    $url = new Url($linkUrl);
    try {
        $url->normalise();
    } catch (InvalidArgumentException $e) {
        error(\Schoolbox\Error\Mapper::ERROR_LINK_FORMAT);
    }

    $linkID = $_POST['linkID'] ?? null;
    $comment = $_POST['comment'] ?? null;
    $visible = isset($_POST['visible']) ? 1 : 0;
    $target = $_POST['target'] ?? '';
    if (is_numeric($linkID)) {
        /* Modify existing link
         */
        try {
            /** @var Link $link */
            $link = LinkMapper::getInstance()->findById($linkID);

            if ($groupHandler->getPermission($link->getFolder()->getId()) < UserAccess::ACCESS_WRITE) {
                error(\Schoolbox\Error\Mapper::ERROR_PERM_CATEGORY_WRITE);
            }
        } catch (Exception $e) {
            error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        /** @var Link $link */
        $link->setUrl($url->getUrl());
        $link->setDescription($description);
        $link->setComment($comment);
        $link->setHidden($visible ? 0 : 1);
        $link->setTarget($target);
        $link->setModified(Alaress_Date_Format::now());

        // If the owner has been deleted - set new owner to the editor
        $owner = $link->getOwner();
        if (!$owner || null === $owner->getId() || $owner->isDeleted()) {
            $link->setOwner($sessionUser);
        }
    } else {
        $link = LinkMapper::getInstance()->createModel();
        $link->setOwner($sessionUser);

        $link->setUrl($url->getUrl());
        $link->setDescription($description);
        $link->setComment($comment);
        $link->setHidden($visible ? 0 : 1);
        $link->setTarget($target);

        // Add to multiple locations
        if (is_array($category)) {
            $linkCollection = LinkMapper::getInstance()->createCollection();
            foreach ($category as $cat) {
                if ($groupHandler->getPermission($cat) < UserAccess::ACCESS_WRITE) {
                    error(\Schoolbox\Error\Mapper::ERROR_PERM_CATEGORY_WRITE);
                }

                $linkDest = clone $link;
                $linkDest->setFolder(FolderMapper::getInstance()->createModel($cat));
                $linkCollection->add($linkDest);
            }
            $link = $linkCollection;
        } else {
            if ($groupHandler->getPermission($category) < UserAccess::ACCESS_WRITE) {
                error(\Schoolbox\Error\Mapper::ERROR_PERM_CATEGORY_WRITE);
            }

            $link->setFolder(FolderMapper::getInstance()->createModel($category));
        }
    }


    LinkMapper::getInstance()->save($link);

    $page->backToTheFuture(2);
} else {
    $move       = $_POST['move'] ?? null;
    $categoryID = $_POST['categoryID'] ?? null;
    $delete     = $_GET['delete'] ?? null;
    if (is_numeric($move ?? null) && is_numeric($categoryID)) {
        /* Move link from one category to another, after checking permission on both from and to categories
         */
        try {
            $link = LinkMapper::getInstance()->findById($move);

            if ($groupHandler->getPermission($link->getFolder()->getId()) < UserAccess::ACCESS_WRITE) {
                $result = "Insufficient permissions";
            } elseif ($groupHandler->getPermission($categoryID) < UserAccess::ACCESS_WRITE) {
                $result = "Insufficient permissions";
            } else {
                $link->setFolder(\Schoolbox\Resource\Folder\Mapper::getInstance()->createModel($categoryID));
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
    } elseif ($delete) {
        try {
            $link = LinkMapper::getInstance()->findById($delete);

            if ($groupHandler->getPermission($link->getFolder()->getId()) < UserAccess::ACCESS_WRITE) {
                error(\Schoolbox\Error\Mapper::ERROR_PERM_CATEGORY_WRITE);
            }

            LinkMapper::getInstance()->delete($link);
        } catch (Exception $e) {
            error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        $redirect = $_GET['redirect'] ?? '';
        if (isset($_REQUEST['ajax'])) {
            die(json_encode(['result' => 'success']));
        } elseif ($redirect == '') {
            $page->backToTheFuture(1);
        } else {
            SitePage::localRedir($redirect);
        }
    } else {
        error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
    }
}
