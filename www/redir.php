<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';
$page = new SitePage;
$url  = '';

$link       = false;
$linkMapper = new \Schoolbox\Resource\LinkMapper();

if ($_GET['url']) {
    try {
        $url = base64_decode($_GET['url']);

        $link = $linkMapper->findOneByColumn('url', $url);
    } catch (\Exception $e) {
        error(101);
    }
} elseif (is_numeric($_GET['id'])) {
    try {
        $link = $linkMapper->findById($_GET['id']);

        $url = $link->getUrl();
    } catch (\Exception $e) {
        error(101);
    }
}

/* Log it
 */
if ($link instanceof \Schoolbox\Resource\Link) {
    $linkMapper->logAction($_SESSION['user'], 'access', $link);
}

if (!empty($url)) {
    header('Location: ' . $url);
    exit;
} else {
    error(2);
}
