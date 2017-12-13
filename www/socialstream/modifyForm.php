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

use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\User;

require_once dirname(__FILE__) . '/../../library/Bootstrap.php';
$page = new \SitePage();

/* Current User
 */
$currentUser = $_SESSION['user'];

// Assume there actually is a logged-in user!
if (!($currentUser instanceof User)) {
    $currentUser = new User();
}

/* Delete a post
 */
if (is_numeric($_GET['delete'])) {
    /* Softdelete the post and the entire thread
     */
    $postMapper = new PostMapper();
    try {
        $post = $postMapper->findById($_GET['delete']);
    }
    catch(\Schoolbox\Mapper\Exception $e){
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
        if ($post->getRootId() == 0) {
            $allPosts = $postMapper->findByColumn('root_id', $_GET['delete']);
        } /* For any threaded stuff that isn't a top-level post
         * delete by parent_id
         */
        else {
            $allPosts = $postMapper->findByColumn('parent_id', $_GET['delete']);
        }

        /* Softdelete the entire thread for the specified post
         */
        $postMapper->delete($allPosts);

        $page->backToTheFuture(1);
    } else {
        error(19);
    }
} /* Otherwise create a post
 */
else {
    /* Compile some misc data
     */
    $data = array_merge($_POST, array(
        'ownerId'     => $currentUser->getId(),
        'requestTime' => \Alaress_Date_Format::now(),
    ));

    /* Check that you have a message, and one of Attachment or URL
     * to prevent you from submitting wholly empty data
     */
    if (!strlen(trim($data['body']))) {
        if (!count($data['attachment']) && !strlen(trim($data['url']))) {
            error(2);
        }
    }

    $url = '';

    if (!empty($_POST['url'])) {
        // give precedence to URL
        $url = $_POST['url'];

    } else {
        // if the attachment points to a folder, use that for the URL
        $str = NodeFactory::SBFS . '.folder:';
        if (strpos($data['attachment'][0], $str) === 0) {
            $url = '/homepage/' . substr($data['attachment'][0], strlen($str));
            unset($data['attachment']);
        }
    }

    /* Create new SocialStream Post
     */
    $factory = new Post\Factory\Creational($url);
    $newPost = $factory->produce($data);

    /* Set up authentication
     * Put your permissions and stuff in here, just like ePortfolio v2
     */
    $instanceMapper = \Component_InstanceMapper::getInstance();
    $instance = $instanceMapper->findByInstanceId($_POST['instanceId']);

    /* Authenticate!
     */
    $authenticator = new Authenticator($newPost, $instance, $currentUser);

    /* Find out if you can collaborate on this
    */
    if ($authenticator->canCollaborate() || $authenticator->canAddComment()) {
        $mapper = new PostMapper();
        $mapper->save($newPost);

        $page->backToTheFuture(1);
    } else {
        error(19);
    }
}
