<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post;

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
$page = new \SitePage();

/* Instantiate something
 */
$factory = new Factory($_POST['type']);

$newPost = $factory->produce();
$newPost->setRootId(0);
$newPost->setParentId(0);
$newPost->setHomepageId($_POST['homepageId']);
$newPost->setOwnerId($_SESSION['id']);
$newPost->setBody($_POST['body']);
$newPost->setCreatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
$newPost->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
$newPost->setDeletedAt(null);

$mapper = new \SocialStream\PostMapper();
$mapper->save($newPost);

