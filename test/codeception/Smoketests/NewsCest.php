<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace Tests\Smoketests;

use Tests\Support\IntegrationTester;
use Tests\Support\Payload;
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

        $i->sendGET("/news/{$draftArticle->getId()}");
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

    /** Publish news with audience Campus Primary
     * Check if only audience from Primary campus can view the news article
     *
     * @param IntegrationTester $i
     */
    public function checkCampusAudience(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Article Testing Campus Audience ' . $timestamp;

        $createDraft = new Payload\News\Create(Querystring::encode('/'));
        $createDraft->setNewsTitle($title);
        $createDraft->setFormParam('body', 'Testing Campus Audience');
        $createDraft->setFormParam('audience', 'campus:IjIi:self');
        $i->amGoingTo('Send request to create the news article draft with Primary campus audience');
        $i->sendRequest($createDraft);
        $article   = $i->grabNewsArticleByTitle($title);
        $articleID = $i->grabFromDatabase('comms_news_article', 'id', ['title' => $title]);

        #WHEN
        $submitRequest = new Payload\News\Accept(Querystring::encode('/'), $article);
        $i->amGoingTo('Send request to accept the news article draft with Primary campus audience');
        $i->sendRequest($submitRequest);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', '/');

        //impersonating Eden Reed(junior student) to ensure the news article is visible to her
        #GIVEN
        $i->impersonateUser(6);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(200);

        //impersonating Alison Chan(primary teacher) to ensure the news article is visible to her
        #GIVEN
        $i->impersonateUser(43);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(200);

        //impersonating Jana Reed(middle student) to ensure the news article is NOT visible to her
        #GIVEN
        $i->impersonateUser(25);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(401);

        //impersonating Jack Coles(secondary teacher) to ensure the news article is NOT visible to her
        #GIVEN
        $i->impersonateUser(22);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(401);
    }

    /** Publish news with roles Teacher and Middle Students
     * Check if only audience teachers and middle students can view the news article
     *
     * @param IntegrationTester $i
     */
    public function checkRolesAudience(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Article Testing Roles Audience ' . $timestamp;

        $createDraft = new Payload\News\Create(Querystring::encode('/'));
        $createDraft->setNewsTitle($title);
        $createDraft->setFormParam('body', 'Testing Roles Audience');
        $createDraft->setFormParam('audience', 'role:IjIi:self,role:IjYi:self');
        $i->amGoingTo('Send request to create the news article draft with teacher and middle student role audience');
        $i->sendRequest($createDraft);
        $article   = $i->grabNewsArticleByTitle($title);
        $articleID = $i->grabFromDatabase('comms_news_article', 'id', ['title' => $title]);

        #WHEN
        $submitRequest = new Payload\News\Accept(Querystring::encode('/'), $article);
        $i->amGoingTo('Send request to accept the news article draft with teacher and middle student roles audience');
        $i->sendRequest($submitRequest);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', '/');

        //impersonating Eden Reed(Jr Student) to ensure the news article is NOT visible to her
        #GIVEN
        $i->impersonateUser(6);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(401);

        //impersonating Alison Chan(primary teacher) to ensure the news article is visible to her
        #GIVEN
        $i->impersonateUser(43);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(200);

        //impersonating Jana Reed(middle student) to ensure the news article is visible to her
        #GIVEN
        $i->impersonateUser(25);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(200);

        //impersonating Jack Coles(secondary teacher) to ensure the news article is visible to him
        #GIVEN
        $i->impersonateUser(22);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(200);

        //impersonating Jenni Reed(parent) to ensure the news article is NOT visible to her
        #GIVEN
        $i->impersonateUser(27);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(401);
    }

    /** Publish news with house Prospect audience (users in role)
     * Check if only Prospect hosue users in role audience can view the news article
     *
     * @param IntegrationTester $i
     */
    public function checkHouseAudience(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Article Testing Prospect Audience (users in role)' . $timestamp;

        $createDraft = new Payload\News\Create(Querystring::encode('/'));
        $createDraft->setNewsTitle($title);
        $createDraft->setFormParam('body', 'Testing Roles Audience');
        $createDraft->setFormParam('audience', 'school_house:IjIi:self');
        $i->amGoingTo('Send request to create the news article draft with house role audience');
        $i->sendRequest($createDraft);
        $article   = $i->grabNewsArticleByTitle($title);
        $articleID = $i->grabFromDatabase('comms_news_article', 'id', ['title' => $title]);

        #WHEN
        $submitRequest = new Payload\News\Accept(Querystring::encode('/'), $article);
        $i->amGoingTo('Send request to accept the news article draft with house roles audience');
        $i->sendRequest($submitRequest);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', '/');

        //impersonating Eden Reed(Jr Student) to ensure the news article is NOT visible to her
        #GIVEN
        $i->impersonateUser(6);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(401);

        //impersonating Jana Reed(middle student) to ensure the news article is visible to her
        #GIVEN
        $i->impersonateUser(25);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(200);

        //impersonating Jack Coles(secondary teacher) to ensure the news article is NOT visible to him
        #GIVEN
        $i->impersonateUser(22);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(401);

        //impersonating Jenni Reed(parent) to ensure the news article is NOT visible to her
        #GIVEN
        $i->impersonateUser(27);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(401);
    }

    /** Publish news with 9A English class 9 (parents of students) audience
     * Check if only parents of students from that class can view the news article
     * keeping as incomplete until I get the answer on how this permission works to either
     * get the test updated or issue fixed (then removing the incomplete from this code)
     * @param IntegrationTester $i
     */
    public function checkClassesAudience(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Article Testing 9A English class 9 (parents of students)' . $timestamp;

        $createDraft = new Payload\News\Create(Querystring::encode('/'));
        $createDraft->setNewsTitle($title);
        $createDraft->setFormParam('body', 'Testing Class Audience');
        $createDraft->setFormParam('audience', 'folder:IjgzIg==:parents_of');
        $i->amGoingTo('Send request to create the news article draft with class audience');
        $i->sendRequest($createDraft);
        $article   = $i->grabNewsArticleByTitle($title);
        $articleID = $i->grabFromDatabase('comms_news_article', 'id', ['title' => $title]);

        #WHEN
        $submitRequest = new Payload\News\Accept(Querystring::encode('/'), $article);
        $i->amGoingTo('Send request to accept the news article draft with class audience');
        $i->sendRequest($submitRequest);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', '/');

        //impersonating Jana Reed(middle student) to ensure the news article is NOT visible to her
        #GIVEN
        $i->impersonateUser(25);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(401);

        //impersonating Jack Coles(teacher with read access to the foler) to ensure the news article is visible to him
        #GIVEN
        $i->impersonateUser(22);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(200);

        //impersonating Jenni Reed(parent) to ensure the news article is visible to her
        #GIVEN
        $i->impersonateUser(27);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(200);
    }

    /** Publish news with Year 9 (teacher of students) audience
     * Check if only teacher of students from that class can view the news article
     *
     * @param IntegrationTester $i
     */
    public function checkYearAudience(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Article Testing Year 9 (teachers of students)' . $timestamp;

        $createDraft = new Payload\News\Create(Querystring::encode('/'));
        $createDraft->setNewsTitle($title);
        $createDraft->setFormParam('body', 'Testing Class Audience');
        $createDraft->setFormParam('audience', 'year:Ijki:teachers_of');
        $i->amGoingTo('Send request to create the news article draft with year level audience');
        $i->sendRequest($createDraft);
        $article   = $i->grabNewsArticleByTitle($title);
        $articleID = $i->grabFromDatabase('comms_news_article', 'id', ['title' => $title]);

        #WHEN
        $submitRequest = new Payload\News\Accept(Querystring::encode('/'), $article);
        $i->amGoingTo('Send request to accept the news article draft with year level audience');
        $i->sendRequest($submitRequest);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', '/');

        //impersonating Jana Reed(year 9 student) to ensure the news article is NOT visible to her
        #GIVEN
        $i->impersonateUser(25);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(401);

        //impersonating Jack Coles(year 9 teacher) to ensure the news article is visible to him
        #GIVEN
        $i->impersonateUser(22);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(200);

        //impersonating Jenni Reed(parent) to ensure the news article is NOT visible to her
        #GIVEN
        $i->impersonateUser(27);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(401);
    }

    /** Publish news by passing a specific user
     * Check if only users targeted can view the news article (Jana Reed and teacher of student, Eden Reed)
     *
     * @param IntegrationTester $i
     */
    public function checkSpecificUsersAudience(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Article Testing targeting specific users' . $timestamp;

        $createDraft = new Payload\News\Create(Querystring::encode('/'));
        $createDraft->setNewsTitle($title);
        $createDraft->setFormParam('body', 'Testing targeting specific users');
        $createDraft->setFormParam('audience', 'user:IjI1Ig==:self,user:IjI1Ig==:teachers_of,user:IjYi:self');
        $i->amGoingTo('Send request to create the news article draft with specific audience');
        $i->sendRequest($createDraft);
        $article   = $i->grabNewsArticleByTitle($title);
        $articleID = $i->grabFromDatabase('comms_news_article', 'id', ['title' => $title]);

        #WHEN
        $submitRequest = new Payload\News\Accept(Querystring::encode('/'), $article);
        $i->amGoingTo('Send request to accept the news article draft with specific audience');
        $i->sendRequest($submitRequest);

        #THEN
        $i->seeResponseCodeIs(302);
        $i->seeHttpHeader('location', '/');

        //impersonating Jana Reed(year 9 student) to ensure the news article is visible to her
        #GIVEN
        $i->impersonateUser(25);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(200);

        //impersonating Jack Coles(year 9 teacher) to ensure the news article is visible to him
        #GIVEN
        $i->impersonateUser(22);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(200);

        //impersonating Jenni Reed(parent) to ensure the news article is NOT visible to her
        #GIVEN
        $i->impersonateUser(27);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(401);

        //impersonating Eden Reed to ensure the news article is visible to her
        #GIVEN
        $i->impersonateUser(6);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(200);

        //impersonating Staff 1 to ensure the news article is NOT visible to them
        #GIVEN
        $i->impersonateUser(65);
        #WHEN
        $i->sendGet('/news/' . $articleID);
        #THEN
        $i->seeResponseCodeIs(401);
    }
}
