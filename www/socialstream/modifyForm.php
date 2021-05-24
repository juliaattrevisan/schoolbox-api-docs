<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream;

use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\User;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var \SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

/* Current User
 */
$currentUser = $_SESSION['user'];

// Assume there actually is a logged-in user!
if (!($currentUser instanceof User)) {
    $currentUser = new User();
}

$postMapper = PostMapper::getInstance();

/* Delete a post
 */
if (is_numeric($_GET['delete'])) {
    /* Softdelete the post and the entire thread
     */
    try {
        $post = $postMapper->findById($_GET['delete']);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(ErrorMapper::ERROR_POST_NOT_FOUND);
    }

    /* Determine permissions
     * Component instance not necessary in this context
     */
    $authenticator = new Authenticator($post, new \Component_Instance(), $currentUser);

    if ($authenticator->canDelete()) {
        $postMapper->delete($post);

        /* This is for top-level posts
         */
        if ($post->getRootId() === 0) {
            $allPosts = $postMapper->findByColumn('root_id', $_GET['delete']);
        } else {
            /* For any threaded stuff that isn't a top-level post
             * delete by parent_id
             */
            $allPosts = $postMapper->findByColumn('parent_id', $_GET['delete']);
        }

        /* Softdelete the entire thread for the specified post
         */
        $postMapper->delete($allPosts);

        $page->backToTheFuture(1);
    } else {
        error(19);
    }
} else {
    /* Otherwise create a post
     *
     * Compile some misc data
     */
    $data = array_merge($_POST, [
        'ownerId'     => $currentUser->getId(),
        'requestTime' => \Alaress_Date_Format::now(),
    ]);

    /* Check that you have contents to prevent you from submitting wholly empty data
     */
    if ('' === trim($data['url']) && '' === trim($data['body'])) {
        error(2);
    }

    /* Create new SocialStream Post
     */
    $newPost = (new Post\Factory\Creational($data['url']))->produce($data);

    /* Set up authentication
     * Put your permissions and stuff in here, just like ePortfolio v2
     */
    $instance = (\Component_InstanceMapper::getInstance())->findByInstanceId($data['instanceId']);

    /* Authenticate!
     */
    $authenticator = new Authenticator($newPost, $instance, $currentUser);

    /* Find out if you can collaborate on this
    */
    if ($authenticator->canCollaborate() || $authenticator->canAddComment()) {
        $postMapper->save($newPost);

        $page->backToTheFuture(1);
    } else {
        error(19);
    }
}
