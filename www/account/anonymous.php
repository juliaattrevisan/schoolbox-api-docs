<?php

use Schoolbox\Auth\Remember\Remember;
use Schoolbox\Error\Mapper as ErrorMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');

$internalUrl = $_GET['page'] ?? '/';
$url = new Url($internalUrl);

// if feature is not enabled, error
if (Config::get('login_anonymous')) {
// Only allow redirects to this site's URLs
    if ($url->isLocalUrl()) {
        //if no user session exists, create it
        if (!($_SESSION['user'] ?? false)) {
            $anonymousUsername = Config::get('login_anonymous');
            $session           = new \Session();
            $session->create($anonymousUsername);

            // Remember the anonymous login
            /** @var Remember $rememberer */
            $rememberer = $kernel->get('session.rememberer');
            $rememberer->rememberUsername($anonymousUsername, Remember::DEFAULT_EXPIRY);

            $page->localRedir($internalUrl);
        } else {
            //user has a session, just redirect
            $page->localRedir($internalUrl);
        }
    } else {
        // invalid redirect url supplied, throwing errors
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
    }
} else {
    throw new \Schoolbox\Mapper\Exception\NotFound();
}
