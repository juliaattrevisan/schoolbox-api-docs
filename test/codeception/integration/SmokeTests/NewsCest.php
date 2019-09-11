<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace SmokeTests;

use IntegrationTester;
use Payload;
use Schoolbox\Comms\News\Querystring;
use function time;

class NewsCest
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


    /** Create draft news using curl request
     *
     * @param IntegrationTester    $i
     */
    public function createDraftNews(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Draft at ' . $timestamp;

        #WHEN
        $newsPayload = new Payload\News\Create(Querystring::encode('/'));
        $newsPayload->setNewsTitle($title);
        $newsPayload->setFormParam('audience', 'role:IjMi:self');
        $i->amGoingTo('Send request to create draft news');
        $i->sendRequest($newsPayload);

        #THEN
        $i->seeResponseCodeIs(303);

        $draftArticle = $i->grabNewsArticleByTitle($title);

        $i->sendGET("https://vagrant.dev.schoolbox.cloud/news/{$draftArticle->getId()}");
        $i->seeResponseCodeIs(200);
    }


    /**
     * Tests we can update a draft article
     *
     * @param IntegrationTester $i
     */
    public function updateDraft(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Draft at ' . $timestamp;

        $createDraft = new Payload\News\Create(Querystring::encode('/'));
        $createDraft->setNewsTitle($title);
        $createDraft->setFormParam('audience', 'role:IjQi:self');
        $i->amGoingTo('Send request to create draft news');
        $i->sendRequest($createDraft);
        $article = $i->grabNewsArticleByTitle($title);

        #WHEN
        $updatedTitle = 'Smoke Test News Title Update at ' . $timestamp;
        $updateDraft = new Payload\News\Update(Querystring::encode('/'), $article);
        $updateDraft->setFormParam('title', $updatedTitle);
        $i->amGoingTo('Send request to update draft news');
        $i->sendRequest($updateDraft);

        # THEN
        $i->seeResponseCodeIs(303);
        $i->grabNewsArticleByTitle($updatedTitle);
    }


    /** Publish draft news into moderation using curl request
     *
     *  Fires off a create step.
     *
     * @param IntegrationTester $i
     */
    public function submitNewsToModeration(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Draft at ' . $timestamp;

        $createDraft = new Payload\News\Create(Querystring::encode('/'));
        $createDraft->setNewsTitle($title);
        $createDraft->setFormParam('audience', 'role:IjQi:self');
        $i->amGoingTo('Send request to create draft news');
        $i->sendRequest($createDraft);
        $article = $i->grabNewsArticleByTitle($title);

        #WHEN
        $submitRequest = new Payload\News\Submit(Querystring::encode('/'), $article);
        $i->amGoingTo('Send request to submit news to moderation queue');
        $i->sendRequest($submitRequest);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', "/news/{$article->getId()}");
    }


    /** Publish draft news using curl request
     *
     *  Fires off a create step.
     *
     * @param IntegrationTester    $i
     */
    public function publishNewsFromDraft(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Publish Form Draft at ' . $timestamp;

        $createDraft = new Payload\News\Create(Querystring::encode('/'));
        $createDraft->setNewsTitle($title);
        $createDraft->setFormParam('audience', 'role:IjQi:self');
        $i->amGoingTo('Send request to create draft news');
        $i->sendRequest($createDraft);
        $article = $i->grabNewsArticleByTitle($title);

        #WHEN
        $submitRequest = new Payload\News\Accept(Querystring::encode('/'), $article);
        $i->amGoingTo('Send request to accept draft news');
        $i->sendRequest($submitRequest);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', '/');
    }


    /** Accept news in moderate using curl request
     *
     *  Fires off a create step and putting the news into moderation
     *
     * @param IntegrationTester    $i
     */
    public function acceptNewsAwaitingModeration(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Draft at ' . $timestamp;

        $createDraft = new Payload\News\Create(Querystring::encode('/'));
        $createDraft->setNewsTitle($title);
        $createDraft->setFormParam('audience', 'role:IjQi:self');
        $i->amGoingTo('Send request to create draft news');
        $i->sendRequest($createDraft);
        $article = $i->grabNewsArticleByTitle($title);

        #WHEN
        $submitRequest = new Payload\News\Submit(Querystring::encode('/'), $article);
        $i->amGoingTo('Send request to submit news to moderation queue');
        $i->sendRequest($submitRequest);

        #WHEN
        $submitRequest = new Payload\News\Accept(Querystring::encode('/'), $article);
        $i->amGoingTo('Send request to accept news awaiting moderation');
        $i->sendRequest($submitRequest);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', '/');
    }


    /** Reject news in moderate using curl request
     *
     *  Fires off a create step and putting the news into moderation
     *
     * @param IntegrationTester    $i
     */
    public function rejectNewsFromModerate(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Reject from Moderate at ' . $timestamp;

        $createDraft = new Payload\News\Create(Querystring::encode('/'));
        $createDraft->setNewsTitle($title);
        $createDraft->setFormParam('audience', 'role:IjQi:self');
        $i->amGoingTo('Send request to create draft news');
        $i->sendRequest($createDraft);
        $article = $i->grabNewsArticleByTitle($title);

        #WHEN
        $submitRequest = new Payload\News\Submit(Querystring::encode('/'), $article);
        $i->amGoingTo('Send request to submit news to moderation queue');
        $i->sendRequest($submitRequest);

        #WHEN
        $rejectRequest = new Payload\News\Reject(Querystring::encode('/'), $article);
        $rejectRequest->setFormParam('rejectReason', 'The reason is valid');
        $i->amGoingTo('Send request to reject news awaiting moderation');
        $i->sendRequest($rejectRequest);

        #THEN
        $i->seeResponseCodeIs(200);
    }


    /** Save news for later using curl request
     *
     *  Fires off a create step and publish step for news.
     *
     * @param IntegrationTester    $i
     */
    public function saveNewsForLater(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Save news for later at ' . $timestamp;

        $createDraft = new Payload\News\Create(Querystring::encode('/'));
        $createDraft->setNewsTitle($title);
        $createDraft->setFormParam('audience', 'role:IjQi:self');
        $i->amGoingTo('Send request to create draft news');
        $i->sendRequest($createDraft);
        $article = $i->grabNewsArticleByTitle($title);

        #WHEN
        $submitRequest = new Payload\News\Accept(Querystring::encode('/'), $article);
        $i->amGoingTo('Send request to accept draft news');
        $i->sendRequest($submitRequest);

        #WHEN
        $addSavedForLater = new Payload\Base('POST', '/news', "/news/saved/{$article->getId()}/add");
        $i->amGoingTo('Send request to add article to saved-for-later');
        $i->sendRequest($addSavedForLater);

        #THEN;
        $i->seeResponseCodeIs(200);

        #WHEN
        $removeSavedForLater = new Payload\Base('POST', '/news', "/news/saved/{$article->getId()}/remove");
        $i->amGoingTo('Send request to remove news for from saved-for-later');
        $i->sendRequest($removeSavedForLater);

        #THEN;
        $i->seeResponseCodeIs(200);
    }


    /** Delete news using curl request
     *
     *  Fires off a create step and publish step for news.
     *
     * @param IntegrationTester    $i
     */
    public function deleteNews(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Delete, I should not exist. at ' . $timestamp;

        $createDraft = new Payload\News\Create(Querystring::encode('/'));
        $createDraft->setNewsTitle($title);
        $createDraft->setFormParam('audience', 'role:IjQi:self');
        $i->amGoingTo('Send request to create draft news');
        $i->sendRequest($createDraft);
        $article = $i->grabNewsArticleByTitle($title);

        #WHEN
        $submitRequest = new Payload\News\Accept(Querystring::encode('/'), $article);
        $i->amGoingTo('Send request to accept draft news');
        $i->sendRequest($submitRequest);

        #WHEN
        $deleteRequest = new Payload\Base('POST', "/news/{$article->getId()}/update", "/news/{$article->getId()}/delete");
        $i->amGoingTo('Send request to delete news');
        $i->sendRequest($deleteRequest);

        #THEN;
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', '/news');
    }
}
