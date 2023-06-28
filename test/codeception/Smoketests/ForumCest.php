<?php

namespace Tests\Smoketests;

use Tests\Support\IntegrationTester;
use Tests\Support\Payload;

class ForumCest
{
   /**
     * Push a login payload to Schoolbox each test to ensure session is active and logged in.
     *
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i): void
    {
        $i->loginAdmin();
        $i->stopFollowingRedirects();
    }


    /** post a thread
     * check the responses
     *
     * @param IntegrationTester $i
     */
    public function postThread(IntegrationTester $i): void
    {
        #GIVEN
        // Creating a forum thread
        $timestamp           = time();
        $title                = 'Forum: Automation API Smoke Test' . $timestamp;
        $body = 'Forum: Automation API Smoke Test Body Details' . $timestamp;
        $categoryId = 83;
        $submitValue = 'Post';
        $forumPayload = new Payload\Forum\CreateForumThread($categoryId, $title, $submitValue);
        #WHEN
        $i->amGoingTo('Send request to add a forum thread');
        $forumPayload->setFormParam('body', $body);
        $i->sendRequest($forumPayload);
        #THEN
        $i->seeResponseCodeIs(302);
    }

    /** modify threads
     * check the responses
     *
     * @depends postThread
     * @param IntegrationTester $i
     */
    public function modifyThread(IntegrationTester $i): void
    {
        #GIVEN
        // Modifying a forum thread
        $timestamp           = time();
        $title                = 'Forum: Automation API Smoke Test UPDATED' . $timestamp;
        $body = 'Forum: Automation API Smoke Test Body Details UPDATED' . $timestamp;
        $categoryId = 83;
        $threadId = $i->grabFromDatabase('forumThread', 'threadID', ['title like' => 'Forum: Automation API Smoke Test%']);
        $postId = $i->grabFromDatabase('forumThread', 'last_post_id', ['title like' => 'Forum: Automation API Smoke Test%']);
        $submitValue = 'Modify';
        $forumPayload = new Payload\Forum\CreateForumThread($categoryId, $title, $submitValue);
        #WHEN
        $i->amGoingTo('Send request to modify a forum thread');
        $forumPayload->setFormParam('body', $body);
        $forumPayload->setFormParam('threadID', $threadId);
        $forumPayload->setFormParam('postID', $postId);
        $i->sendRequest($forumPayload);
        #THEN
        $i->seeResponseCodeIs(302);
    }

    /** reply threads
     * check the responses
     *
     * @depends postThread
     * @param IntegrationTester $i
     */
    public function replyThread(IntegrationTester $i): void
    {
        #GIVEN
        // Modifying a forum thread
        $timestamp           = time();
        $title                = 'Forum: Automation API Smoke Test UPDATED' . $timestamp;
        $body = 'Forum Reply: Automation API Smoke Test Replying a Thread' . $timestamp;
        $categoryId = 83;
        $threadId = $i->grabFromDatabase('forumThread', 'threadID', ['title like' => 'Forum: Automation API Smoke Test%']);
        $quoteId = $i->grabFromDatabase('forumPost', 'parentID', ['threadID' => $threadId]);
        $submitValue = 'Post';
        $forumPayload = new Payload\Forum\CreateForumThread($categoryId, $title, $submitValue);
        #WHEN
        $i->amGoingTo('Send request to modify a forum thread');
        $forumPayload->setFormParam('body', $body);
        $forumPayload->setFormParam('threadID', $threadId);
        $forumPayload->setFormParam('quoteID', $quoteId);
        $i->sendRequest($forumPayload);
        #THEN
        $i->seeResponseCodeIs(302);
    }

    /** delete a post and a thread
     * check the responses
     *
     * @depends modifyThread
     * @param IntegrationTester $i
     */
    public function deletePostThread(IntegrationTester $i): void
    {
        #GIVEN
        // Deleting a post
        $categoryId = 83;
        $threadId = $i->grabFromDatabase('forumThread', 'threadID', ['title like' => 'Forum: Automation API Smoke Test UPDATED%']);
        $postID = $i->grabFromDatabase('forumPost', 'postID', ['threadID' => $threadId, 'body like' => 'Forum Reply: Automation API Smoke Test Replying a Thread%']);
        $forumPayload = new Payload\Forum\DeleteForumThread($threadId, $postID, 'deletePost');
        #WHEN
        $i->amGoingTo('Send request to delete a forum post');
        $i->sendRequest($forumPayload);
        #THEN
        $i->seeResponseCodeIs(302);

        #GIVEN
        // There is no comments anymore in the thread the forum/folder_id page is still accessible SA-13919
        $i->sendGet("/forum/" . $categoryId);
        $i->seeResponseCodeIs(200);

        #GIVEN
        // Deleting a thread
        $threadId = $i->grabFromDatabase('forumThread', 'threadID', ['title like' => 'Forum: Automation API Smoke Test UPDATED%']);
        $forumPayload = new Payload\Forum\DeleteForumThread($threadId, $threadId, 'deleteThread');
        #WHEN
        $i->amGoingTo('Send request to delete a forum thread');
        $i->sendRequest($forumPayload);
        #THEN
        $i->seeResponseCodeIs(302);
    }
}
