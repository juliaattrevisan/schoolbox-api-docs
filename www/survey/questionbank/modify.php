<?php
/**
 * Move
 *
 * Moves a question around in the Questionbank Category tree, reassigning
 * parent_ids where appropriate based on $_GET['position']
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once('../../../library/Bootstrap.php');

$page = new SitePage();
$groupHandler = new Group();

if ($groupHandler->isStaff() || $groupHandler->isSuperuser()) {
    $categoryHandler = new Survey_Question_Category();

    $newCategory = array(
        'question_category_id' => $_POST['questionCategoryId'],
        'name' => $_POST['name'],
    );

    $categoryHandler->update($newCategory);
    $page->backToTheFuture(1);
} else {
    error(126);
}
