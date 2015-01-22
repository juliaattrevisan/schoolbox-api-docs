<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';
$page = new SitePage;

$url = '';

if ($_GET['url']) {
    $url = base64_decode($_GET['url']);
} elseif (is_numeric($_GET['id'])) {
    // lookup link if id given
    try {
        $linkMapper = new \Schoolbox\Resource\LinkMapper();
        $link       = $linkMapper->findById($_GET['id']);

        $url = $link->getUrl();
    } catch (\Exception $e) {
        error(101);
    }

    if ($link) {
        $linkMapper->logAction($_SESSION['user'], 'access', $link);
    }
}

if (!empty($url)) {
    header('Location: ' . $url);
    exit;
} else {
    error(2);
}
