<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream;

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
$page = new \SitePage();

/* Set up authentication
 * Put your permissions and stuff in here, just like ePortfolio v2
 */
$instanceMapper = \Component_InstanceMapper::getInstance();

/* Seriously?
 */
$instanceArray = $instanceMapper->findByInstanceId($_POST['instanceId']);
$instance = $instanceArray[$_POST['instanceId']];

/* Current User
 */
$currentUser = \Schoolbox\UserMapper::getInstance()->findById($_SESSION['id']);

/* Authenticate!
 */
$authenticator = new Authenticator($instance, $currentUser);

/* Find out if you can collaborate on this
*/
if ($authenticator->canCollaborate()) {
}

/* Find out if you can comment on this
 */
if ($authenticator->canComment()) {
}


print_r($_SESSION);
new \Alaress_Probe($instance);
die();

/* Create new SocialStream Post
 */
$factory = new Post\Factory($_POST['type']);

$newPost = $factory->produce();
$newPost->setRootId($_POST['rootId']);
$newPost->setParentId($_POST['parentId']);
$newPost->setHomepageId($_POST['homepageId']);
$newPost->setOwnerId($_SESSION['id']);
$newPost->setBody($_POST['body']);
$newPost->setCreatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
$newPost->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
$newPost->setDeletedAt(null);

$mapper = new PostMapper();
$mapper->save($newPost);

$page->backToTheFuture(1);