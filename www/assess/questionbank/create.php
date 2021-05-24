<?php

/**
 * Create
 *
 * Creates a Questionbank Category
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(126);
}

$newCategory = array(
    'name' => $_POST['name'],
    'parent_id' => $_POST['parentId'],
);

$categoryHandler = new Assess_Question_Category();
$categoryHandler->create($newCategory);

/* Back to the future -_-
 */
SitePage::backToTheFuture(1);
