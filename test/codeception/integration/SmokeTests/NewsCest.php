<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace SmokeTests;

use IntegrationTester;
use Payload;

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
    }

    /** Create draft news using curl request
     *
     * @param IntegrationTester  $i
     * @param Payload\CreateNews $newsPayload
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function createDraftNews(IntegrationTester $i, Payload\CreateNews $newsPayload): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Draft at ' . $timestamp;

        #WHEN
        $newsPayload->setNewsTitle($title);
        $response      = $i->sendCurlRequest($newsPayload, true, 'Send request to create draft news');
        $draft_article = $i->getNewsArticleByTitle($title);

        $expectedUrlsReturned = [
            ['https://vagrant.dev.schoolbox.cloud/news/' . $draft_article->getId() . '?returnUrl=Lw%253D%253D' => '303'],
            [IntegrationTester::NO_REDIRECT => '200'],
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Publish draft news into moderation using curl request
     *
     *  Fires off a create step.
     *
     * @param IntegrationTester  $i
     * @param Payload\CreateNews $newsPayload
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function publishDraftToModerate(IntegrationTester $i, Payload\CreateNews $newsPayload): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Moderate at ' . $timestamp;

        $newsPayload->setNewsTitle($title);
        $i->sendCurlRequest($newsPayload, false, 'Send request to create draft news');
        $article_id = $i->getNewsArticleByTitle($title)->getId();

        #WHEN
        $newsPayload->setPage('/news/moderation/' . $article_id . '/submit');
        $newsPayload->setReferer('/news/' . $article_id . '/update');
        $newsPayload->removeFormParam('draft');
        $newsPayload->removeFormParam('returnUrl');

        $response             = $i->sendCurlRequest($newsPayload, true, 'Send request to publish draft news to moderate');
        $expectedUrlsReturned = [
            ['https://vagrant.dev.schoolbox.cloud/news/' . $article_id => '302'],
            [IntegrationTester::NO_REDIRECT => '200'],
        ];
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Publish draft news using curl request
     *
     *  Fires off a create step.
     *
     * @param IntegrationTester  $i
     * @param Payload\CreateNews $newsPayload
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function publishNewsFromDraft(IntegrationTester $i, Payload\CreateNews $newsPayload): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Publish Form Draft at ' . $timestamp;

        $newsPayload->setNewsTitle($title);
        $i->sendCurlRequest($newsPayload, false, 'Send request to create draft news');
        $article_id = $i->getNewsArticleByTitle($title)->getId();

        #WHEN
        $newsPayload->setPage('/news/moderation/' . $article_id . '/accept');
        $newsPayload->setReferer('/news/' . $article_id . '/update');
        $newsPayload->removeFormParam('draft');
        $newsPayload->removeFormParam('returnUrl');

        $response             = $i->sendCurlRequest($newsPayload, true, 'Send request to publish draft news');
        $expectedUrlsReturned = [
            ['https://vagrant.dev.schoolbox.cloud/news/' . $article_id => '302'],
            [IntegrationTester::NO_REDIRECT => '200'],
        ];
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Accept news in moderate using curl request
     *
     *  Fires off a create step and putting the news into moderation
     *
     * @param IntegrationTester  $i
     * @param Payload\CreateNews $newsPayload
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function publishNewsFromModerate(IntegrationTester $i, Payload\CreateNews $newsPayload): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Publish Form Moderate at ' . $timestamp;

        $newsPayload->setNewsTitle($title);
        $i->sendCurlRequest($newsPayload, false, 'Send request to create draft news');
        $article_id = $i->getNewsArticleByTitle($title)->getId();

        $newsPayload->setPage('/news/moderation/' . $article_id . '/submit');
        $newsPayload->setReferer('/news/' . $article_id . '/update');
        $newsPayload->removeFormParam('draft');
        $newsPayload->removeFormParam('returnUrl');
        $i->sendCurlRequest($newsPayload, false, 'Send request to publish draft news to moderate');

        #WHEN
        $newsPayload->setPage('/news/moderation/' . $article_id . '/accept');
        $newsPayload->setReferer('/news/' . $article_id . '/update');
        $newsPayload->clearAllFormParams();
        $newsPayload->setFormParam('articleID', $article_id);
        $response             = $i->sendCurlRequest($newsPayload, true, 'Send request to accept news from moderate');
        $expectedUrlsReturned = [
            ['https://vagrant.dev.schoolbox.cloud/news/' . $article_id => '302'],
            [IntegrationTester::NO_REDIRECT => '200'],
        ];
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Reject news in moderate using curl request
     *
     *  Fires off a create step and putting the news into moderation
     *
     * @param IntegrationTester  $i
     * @param Payload\CreateNews $newsPayload
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function rejectNewsFromModerate(IntegrationTester $i, Payload\CreateNews $newsPayload): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Reject from Moderate at ' . $timestamp;

        $newsPayload->setNewsTitle($title);
        $i->sendCurlRequest($newsPayload, false, 'Send request to create draft news');
        $article_id = $i->getNewsArticleByTitle($title)->getId();

        $newsPayload->setPage('/news/moderation/' . $article_id . '/submit');
        $newsPayload->setReferer('/news/' . $article_id . '/update');
        $newsPayload->removeFormParam('draft');
        $newsPayload->removeFormParam('returnUrl');
        $i->sendCurlRequest($newsPayload, false, 'Send request to publish draft news to moderate');

        #WHEN
        $newsPayload->setPage('/news/moderation/' . $article_id . '/reject');
        $newsPayload->setReferer('/news/' . $article_id . '/update');
        $newsPayload->clearAllFormParams();
        $newsPayload->setFormParam('articleID', $article_id);
        $newsPayload->setFormParam('rejectReason', 'The reason is valid');
        $response             = $i->sendCurlRequest($newsPayload, true, 'Send request to reject news from moderate');
        $expectedUrlsReturned = [[IntegrationTester::NO_REDIRECT => '200']];

        #THEN;
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Save news for later using curl request
     *
     *  Fires off a create step and publish step for news.
     *
     * @param IntegrationTester  $i
     * @param Payload\CreateNews $newsPayload
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function saveNewsForLater(IntegrationTester $i, Payload\CreateNews $newsPayload): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Save news for later at ' . $timestamp;

        $newsPayload->setNewsTitle($title);
        $i->sendCurlRequest($newsPayload, false, 'Send request to create draft news');
        $article_id = $i->getNewsArticleByTitle($title)->getId();

        $newsPayload->setPage('/news/moderation/' . $article_id . '/accept');
        $newsPayload->setReferer('/news/' . $article_id . '/update');
        $newsPayload->removeFormParam('draft');
        $newsPayload->removeFormParam('returnUrl');
        $i->sendCurlRequest($newsPayload, false, 'Send request to publish draft news');

        #WHEN
        $newsPayload->setPage('/news/saved/' . $article_id . '/add');
        $newsPayload->setReferer('/news');
        $newsPayload->clearAllFormParams();
        $response             = $i->sendCurlRequest($newsPayload, true, 'Send request to save news for later');
        $expectedUrlsReturned = [[IntegrationTester::NO_REDIRECT => '200']];
        #THEN;
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Delete news using curl request
     *
     *  Fires off a create step and publish step for news.
     *
     * @param IntegrationTester  $i
     * @param Payload\CreateNews $newsPayload
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function deleteNews(IntegrationTester $i, Payload\CreateNews $newsPayload): void
    {
        #GIVEN
        $timestamp = time();
        $title     = 'Smoke Test News Title Delete, I should not exist. at ' . $timestamp;

        $newsPayload->setNewsTitle($title);
        $i->sendCurlRequest($newsPayload, false, 'Send request to create draft news');
        $article_id = $i->getNewsArticleByTitle($title)->getId();

        $newsPayload->setPage('/news/moderation/' . $article_id . '/accept');
        $newsPayload->setReferer('/news/' . $article_id . '/update');
        $newsPayload->removeFormParam('draft');
        $newsPayload->removeFormParam('returnUrl');
        $i->sendCurlRequest($newsPayload, false, 'Send request to publish draft news');

        #WHEN
        $newsPayload->setPage('/news/' . $article_id . '/delete');
        $newsPayload->setReferer('/news/' . $article_id . '/update');
        $newsPayload->clearAllFormParams();
        $newsPayload->setFormParam('articleID', $article_id);
        $response             = $i->sendCurlRequest($newsPayload, true, 'Send request to delete news');
        $expectedUrlsReturned = [
            ['https://vagrant.dev.schoolbox.cloud/news' => '302'],
            [IntegrationTester::NO_REDIRECT => '200'],
        ];

        #THEN;
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }
}
