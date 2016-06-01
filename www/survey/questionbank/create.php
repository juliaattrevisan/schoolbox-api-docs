<?php
/**
 * Create
 *
 * Creates a Questionbank Category
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once('../library/Bootstrap.php');

$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(126);
}

$newCategory = array(
    'name' => $_POST['name'],
    'parent_id' => $_POST['parentId'],
);

$categoryHandler = new Survey_Question_Category();
$categoryHandler->create($newCategory);

/* Back to the future -_-
 */
$page = new SitePage();
$page->backToTheFuture(1);
