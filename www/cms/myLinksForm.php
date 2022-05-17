<?php

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$myLinksHandler = new MyLinks();

$existing = is_numeric($_POST['linkID'] ?? null) ? $myLinksHandler->getByID($_POST['linkID']) : null;

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if ($existing && ((int)$existing['owner'] !== $sessionUser->getId())) {
    error(\Schoolbox\Error\Mapper::ERROR_PERM_LINK);
}

if (isset($_REQUEST['url']) && $_REQUEST['url'] && isset($_REQUEST['description']) && $_REQUEST['description']) {
    $urlFormatter = new Url($_REQUEST['url']);
    try {
        $urlFormatter->normalise();
    } catch (InvalidArgumentException $e) {
        error(\Schoolbox\Error\Mapper::ERROR_LINK_FORMAT);
    }

    $url = Scrub::text($urlFormatter->getUrl());

    /* Create/update
     */

    if ($existing) {
        $existing['url']         = $url;
        $existing['description'] = $_REQUEST['description'];

        $myLinksHandler->update($existing);
    } else {
        $myLinksHandler->create([
            'owner'       => $_SESSION['id'],
            'url'         => $url,
            'description' => $_REQUEST['description'],
        ]);
    }
} else {
    if (isset($_GET['delete']) && $existing) {
        $myLinksHandler->delete($existing['id']);
    }
}

if ($_GET['return'] ?? false) {
    SitePage::backToTheFuture(1);
} else {
    SitePage::localRedir('/cms/myLinks');
}
