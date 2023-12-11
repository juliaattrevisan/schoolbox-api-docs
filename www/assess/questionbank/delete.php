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

/* There's no real allegory to our legacy Question Bank - in learnosity era,
 * every item is in the itembank already. The only place you access that is
 * via learnosity quiz content authoring, but we don't really have the args
 * to find a particular assessment to which a person might be redirected.
 */
$this->pageNotFound();

//// not in SitePage context?
//if (!isset($this)) {
//    http_response_code(404);
//    die('No direct access permitted');
//}
///** @var SitePage $this */
//$this->initSession();
//
//$groupHandler = new Group();
//
//if (!$groupHandler->isStaff()) {
//    error(126);
//}
//
//$categoryHandler = new Assess_Question_Category();
//
//if (is_numeric($this->arg[0])) {
//    $categoryHandler->delete($categoryHandler->getByID($this->arg[0]));
//}
//
///* Back to the future -_-
// */
//SitePage::backToTheFuture(1);
