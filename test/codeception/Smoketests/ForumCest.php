<?php

namespace Tests\Smoketests;

use Tests\Support\IntegrationTester;
use Tests\Support\Payload;

class ForumCest
{
    private int $timestamp = 0;
    private const CATEGORY_ID = 83;

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
     * @throws \JsonException
     */
    public function postThread(IntegrationTester $i): void
    {
        #GIVEN
        // Creating a forum thread
        $this->timestamp = time();
        $title = 'Forum: Automation API Smoke Test ' . $this->timestamp;
        $body = 'Forum: Automation API Smoke Test Body Details ' . $this->timestamp;
        $submitValue = 'Post';
        $forumPayload = new Payload\Forum\CreateForumThread(self::CATEGORY_ID, $title, $submitValue);
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
     * @throws \JsonException
     */
    public function modifyThread(IntegrationTester $i): void
    {
        #GIVEN
        // Modifying a forum thread
        $title = 'Forum: Automation API Smoke Test UPDATED ' . $this->timestamp;
        $body = 'Forum: Automation API Smoke Test Body Details UPDATED ' . $this->timestamp;
        $threadId = $i->grabFromDatabase('forumThread', 'threadID', ['title' => "Forum: Automation API Smoke Test $this->timestamp"]);
        $postId = $i->grabFromDatabase('forumThread', 'last_post_id', ['title' => "Forum: Automation API Smoke Test $this->timestamp"]);
        $submitValue = 'Modify';
        $forumPayload = new Payload\Forum\CreateForumThread(self::CATEGORY_ID, $title, $submitValue);
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
     * @throws \JsonException
     */
    public function replyThread(IntegrationTester $i): void
    {
        #GIVEN
        // Modifying a forum thread
        $title = 'Forum: Automation API Smoke Test UPDATED ' . $this->timestamp;
        $body = 'Forum Reply: Automation API Smoke Test Replying a Thread ' . $this->timestamp;
        $threadId = $i->grabFromDatabase('forumThread', 'threadID', ['title' => "Forum: Automation API Smoke Test UPDATED $this->timestamp"]);
        $quoteId = $i->grabFromDatabase('forumPost', 'parentID', ['threadID' => $threadId]);
        $submitValue = 'Post';
        $forumPayload = new Payload\Forum\CreateForumThread(self::CATEGORY_ID, $title, $submitValue);
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
     * @throws \JsonException
     */
    public function deletePostThread(IntegrationTester $i): void
    {
        #GIVEN
        // Deleting a post
        $threadId = $i->grabFromDatabase('forumThread', 'threadID', ['title' => "Forum: Automation API Smoke Test UPDATED $this->timestamp"]);
        $postID = $i->grabFromDatabase('forumPost', 'postID', ['threadID' => $threadId, 'body' => "Forum Reply: Automation API Smoke Test Replying a Thread $this->timestamp"]);
        $forumPayload = new Payload\Forum\DeleteForumThread($threadId, $postID, 'deletePost');
        #WHEN
        $i->amGoingTo('Send request to delete a forum post');
        $i->sendRequest($forumPayload);
        #THEN
        $i->seeResponseCodeIs(302);

        #GIVEN
        // There is no comments anymore in the thread the forum/folder_id page is still accessible SA-13919
        $i->sendGet("/forum/" . self::CATEGORY_ID);
        $i->seeResponseCodeIs(200);

        #GIVEN
        // Deleting a thread
        $threadId = $i->grabFromDatabase('forumThread', 'threadID', ['title' => "Forum: Automation API Smoke Test UPDATED $this->timestamp"]);
        $forumPayload = new Payload\Forum\DeleteForumThread($threadId, $threadId, 'deleteThread');
        #WHEN
        $i->amGoingTo('Send request to delete a forum thread');
        $i->sendRequest($forumPayload);
        #THEN
        $i->seeResponseCodeIs(302);
    }
}
