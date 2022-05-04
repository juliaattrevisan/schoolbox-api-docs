<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

if (is_readable($_FILES['file']['tmp_name'])) {
    try {
        $imageResource = Schoolbox\Resource\Storage\Mapper::getInstance()->accept(
            $_FILES['file']['name'],
            $_FILES['file']['tmp_name']
        );
        $hash = $imageResource->getHash();
        if (isset($_POST['return'])) {
            SitePage::localRedir($_POST['return'] . '?hash=' . $hash);
        } else {
            $page->backToTheFuture(2);
        }
    } catch (Exception $ex) {
        //
        // Set an error message somehow. (there's no flash message display)
        if (isset($_POST['return'])) {
            SitePage::localRedir($_POST['return'] . '?' . http_build_query(array('error' => $ex->getCode())));
        } else {
            $page->backToTheFuture(2);
        }
    }
} else {
    error(2);
}
