<?php
require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$page = new SitePage();

if (!Config::get('webdav_enable')) {
    error(2);
}

$folderHandler = PortfolioWebDAV::factory();
$isAjax = isset($_REQUEST['ajax']) && $_REQUEST['ajax'];

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

    if ($isAjax) {
        die(json_encode(['result' => 'success']));
    } else {
        SitePage::localRedir('/drives/' . $folder['parent']);
    }
} elseif (is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
    /* Move file from one category to another, after checking permission on both from and to categories */
    if ($_POST['move'] === $_POST['categoryID']) {
        respondFailure($isAjax, "Cannot move folder to itself. ID: {$_POST['move']}");
    }

    $folder = $folderHandler->getByID($_POST['move']);
    if (!$folder) {
        respondFailure($isAjax, "Destination folder not found: {$_POST['move']}");
    }

    if ($folder['owner'] != $_SESSION['id']) {
        respondFailure($isAjax, 'Insufficient permissions');
    }
    $folderHandler->update(array('id' => $_POST['move'], 'parent' => $_POST['categoryID']));
} elseif (is_numeric($_POST['portfolioID'])) {
    $_SESSION['portfolioID'] = $_POST['portfolioID'];
}

/**
 * Send a failure response back to the browser and terminate script execution
 *
 * @param bool   $isAjax
 * @param string $message
 * @param int    $code
 */
function respondFailure($isAjax, $message, $code = 400)
{
    http_response_code($code);
    if ($isAjax) {
        header('Content-type:application/json');
        die(json_encode([
            'result'  => 'failure',
            'message' => $message,
        ]));
    }
    die($message);
}

