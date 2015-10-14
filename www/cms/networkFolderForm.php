<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();

if (!Cfg::get('webdav_enable')) {
    error(2);
}

$folderHandler = PortfolioWebDAV::factory();

if (isset($_POST['name'])) {
    $folder = array(
        'parent' => $_POST['parent'],
        'name' => $_POST['name'],
    );

    if (is_numeric($_POST['folderID'])) {
        /* check user owns folder
           */
        $current = $folderHandler->getByID($_POST['folderID']);

        if ($current['owner'] != $_SESSION['id']) {
            $page->error(47);
        }

        $folder['id'] = $_POST['folderID'];
        $folderHandler->update($folder);
    } else {
        $folder['id'] = $folderHandler->create($folder);
    }

    SitePage::localRedir('/drives/' . $folder['id']);
} elseif (is_numeric($_GET['delete'])) {

    if (!$folder = $folderHandler->getByID($_GET['delete'])) {
        error(2);
    }

    if ($folder['owner'] != $_SESSION['id']) {
        $page->error(48);
    }

    $folderHandler->delete($_GET['delete']);

    SitePage::localRedir('/drives/' . $folder['parent']);
} elseif (is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
    /* Move file from one category to another, after checking permission on both from and to categories */
    if ($_POST['move'] == $_POST['categoryID'] || !$folder = $folderHandler->getByID($_POST['move'])) {
        echo "Failed";
    }

    if ($folder['owner'] != $_SESSION['id']) {
        echo "Insufficient permssions";
    }

    $folderHandler->update(array('id' => $_POST['move'], 'parent' => $_POST['categoryID']));
} elseif (is_numeric($_POST['portfolioID'])) {
    $_SESSION['portfolioID'] = $_POST['portfolioID'];
}

