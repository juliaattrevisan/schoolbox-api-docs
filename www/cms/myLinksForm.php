<?php

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$bookmarkMapper = \Schoolbox\Bookmark\Mapper::getInstance();

try {
    /** @var \Schoolbox\Bookmark\Model|null $existing */
    $existing = is_numeric($_POST['linkID'] ?? null) ? $bookmarkMapper->findById($_POST['linkID']) : null;
} catch (\Schoolbox\Mapper\Exception $e) {
    $existing = null;
}

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if ($existing instanceof \Schoolbox\Bookmark\Model && ($existing->getOwner() !== $sessionUser->getId())) {
    error(\Schoolbox\Error\Mapper::ERROR_PERM_LINK);
}

if (isset($_POST['url']) && $_POST['url'] && isset($_POST['description']) && $_POST['description']) {
    $urlFormatter = new Url($_POST['url']);
    try {
        $urlFormatter->normalise();
    } catch (InvalidArgumentException $e) {
        error(\Schoolbox\Error\Mapper::ERROR_LINK_FORMAT);
    }

    $url = Scrub::text($urlFormatter->getUrl());

    /* Create/update
     */

    if ($existing instanceof \Schoolbox\Bookmark\Model) {
        $existing->setUrl($url);
        $existing->setDescription($_POST['description']);
        $bookmarkMapper->save($existing);
    } else {
        $newLink = $bookmarkMapper->createModel()
            ->setOwner($_SESSION['id'])
            ->setUrl($url)
            ->setDescription($_POST['description']);
        $bookmarkMapper->save($newLink);
    }
} else {
    if (isset($_GET['delete']) && $existing) {
        $bookmarkMapper->delete($existing);
    }
}

if ($_GET['return'] ?? false) {
    SitePage::backToTheFuture(1);
} else {
    SitePage::localRedir('/cms/myLinks');
}
