<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Resource\Link;
use Schoolbox\Resource\LinkMapper;

$page         = new SitePage();
$groupHandler = new Group();


if ($_POST['url'] && $_POST['description'] && (is_numeric($_POST['category']) || is_array($_POST['category']))) {
    $url = new Url($_POST['url']);
    if (!($url->isURL() || $url->isLocalURL())) {
        error(139);
    }
    if (is_numeric($_POST['linkID'])) {
        /* Modify existing link
         */
        try {
            /** @var Link $link */
            $link = LinkMapper::getInstance()->findById($_POST['linkID']);

            if ($groupHandler->getPermission($link->getFolder()->getId()) < WRITE_ACCESS) {
                error(6);
            }
        } catch (Exception $e) {
            error(2);
        }

        $link->setUrl($_POST['url']);
        $link->setDescription($_POST['description']);
        $link->setComment($_POST['comment']);
        $link->setHidden($_POST['hidden'] ? true : false);
        $link->setModified(Alaress_Date_Format::now());

    } else {
        /** @var Link $link */
        $link = LinkMapper::getInstance()->createModel();
        $link->setOwner($_SESSION['id']);

        $link->setUrl($_POST['url']);
        $link->setDescription($_POST['description']);
        $link->setComment($_POST['comment']);
        $link->setHidden($_POST['hidden'] ? true : false);


        // Add to multiple locations
        if (is_array($_POST['category'])) {
            $linkCollection = LinkMapper::getInstance()->createCollection();
            foreach ($_POST['category'] as $cat) {
                if ($groupHandler->getPermission($cat) < WRITE_ACCESS) {
                    error(6);
                }

                $linkDest = clone $link;
                $linkDest->setFolder(CategoryMapper::getInstance()->createModel($cat));
                $linkCollection->add($linkDest);
            }
            $link = $linkCollection;
        } else {
            if ($groupHandler->getPermission($_POST['category']) < WRITE_ACCESS) {
                error(6);
            }

            $link->setFolder(CategoryMapper::getInstance()->createModel($_POST['category']));
        }
    }


    LinkMapper::getInstance()->save($link);

    $page->backToTheFuture(2);
} else {
    if (is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
        /* Move link from one category to another, after checking permission on both from and to categories
         */
        try {
            $link = LinkMapper::getInstance()->findById($_POST['move']);

            if ($groupHandler->getPermission($link->getFolder()->getId()) < WRITE_ACCESS) {
                $result = "Insufficient permssions";
            } elseif ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS) {
                $result = "Insufficient permssions";
            } else {
                $link->setFolder(\Schoolbox\Resource\CategoryMapper::getInstance()->createModel($_POST['categoryID']));
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

            if ($groupHandler->getPermission($link->getFolder()->getId()) < WRITE_ACCESS) {
                error(6);
            }

            LinkMapper::getInstance()->delete($link);
        } catch (Exception $e) {
            error(2);
        }

        if (isset($_REQUEST['ajax'])) {
            die(json_encode(['result' => 'success']));
        } elseif ($_GET['redirect'] == '') {
            $page->backToTheFuture(1);
        } else {
            SitePage::localRedir($_GET['redirect']);
        }
    } else {
        error(2);
    }
}
