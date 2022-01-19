<?php

use Schoolbox\Auth\Remember\Remember;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');

$internalUrl = $_GET['page'] ?: '/';
if (Config::get('login_anonymous') && !$_SESSION['user']) {
    $anonymousUsername = Config::get('login_anonymous');
    $session           = new \Session();
    $session->create($anonymousUsername);

    // Remember the anonymous login
    /** @var Remember $rememberer */
    $rememberer = $kernel->get('session.rememberer');
    $rememberer->rememberUsername($anonymousUsername, Remember::DEFAULT_EXPIRY);

    $page->localRedir($internalUrl);
} else {
    $page->localRedir($internalUrl);
}
