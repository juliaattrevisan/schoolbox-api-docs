<?php

/**
 * Delete
 *
 * Softdeletes a Questionbank Category
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// not in SitePage context?
if (!isset($this)) {
    http_response_code(404);
    die('No direct access permitted');
}
/** @var SitePage $this */

$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(126);
}

$categoryHandler = new Survey_Question_Category();

if (is_numeric($this->arg[0])) {
    $categoryHandler->delete($categoryHandler->getByID($this->arg[0]));
}

/* Back to the future -_-
 */
$page = new SitePage();
$page->backToTheFuture(1);
