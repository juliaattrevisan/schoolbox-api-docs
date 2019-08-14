<?php

namespace SmokeTests;

use GuzzleHelper\GuzzleConstants;
use IntegrationTester;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\GuzzleException;
use Page\Common;
use Payload\CreateNews as NewsPayload;

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class NewsCest {

    /** Create draft news using curl request
     *
     * @param IntegrationTester $i
     * @param NewsPayload       $newsPayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function createDraftNews(IntegrationTester $i, NewsPayload $newsPayload){
        #GIVEN
        $i->loginAdmin2();
        $timestamp=time();
        $title = 'Smoke Test News Title Draft at '.$timestamp;

        #WHEN
        $newsPayload->setNewsTitle($title);
        $createDraftRequest = $newsPayload->payloadToArray();
        $response = $i->sendCurlRequest($createDraftRequest['method'], $createDraftRequest['page'], $createDraftRequest['payload'], true, 'Send request to create draft news');
        $draft_article=$i->getNewsArticleByTitle($title);

        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/news/'.$draft_article->getId().'?returnUrl=Lw%253D%253D'=>'303'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];

        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Publish draft news into moderation using curl request
     *
     *  Fires off a create step.
     *
     * @param IntegrationTester $i
     * @param NewsPayload       $newsPayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function publishDraftToModerate(IntegrationTester $i, NewsPayload $newsPayload){
        #GIVEN
        $i->loginAdmin2();
        $timestamp=time();
        $title = 'Smoke Test News Title Moderate at '.$timestamp;

        $newsPayload->setNewsTitle($title);
        $createDraftRequest = $newsPayload->payloadToArray();
        $i->sendCurlRequest($createDraftRequest['method'], $createDraftRequest['page'], $createDraftRequest['payload'], false, 'Send request to create draft news');
        $article_id=$i->getNewsArticleByTitle($title)->getId();

        #WHEN
        $newsPayload->setPage('/news/moderation/'.$article_id.'/submit');
        $newsPayload->setReferer('/news/'.$article_id.'/update');
        $newsPayload->removeFormParam('draft');
        $newsPayload->removeFormParam('returnUrl');
        $moderateDraftRequest = $newsPayload->payloadToArray();

        $response = $i->sendCurlRequest($moderateDraftRequest['method'], $moderateDraftRequest['page'], $moderateDraftRequest['payload'], true, 'Send request to publish draft news to moderate');
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/news/'.$article_id=>'302'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Publish draft news using curl request
     *
     *  Fires off a create step.
     *
     * @param IntegrationTester $i
     * @param NewsPayload       $newsPayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function publishNewsFromDraft(IntegrationTester $i, NewsPayload $newsPayload){
        #GIVEN
        $i->loginAdmin2();
        $timestamp=time();
        $title = 'Smoke Test News Title Publish Form Draft at '.$timestamp;

        $newsPayload->setNewsTitle($title);
        $createDraftRequest = $newsPayload->payloadToArray();
        $i->sendCurlRequest($createDraftRequest['method'], $createDraftRequest['page'], $createDraftRequest['payload'], false, 'Send request to create draft news');
        $article_id=$i->getNewsArticleByTitle($title)->getId();

        #WHEN
        $newsPayload->setPage('/news/moderation/'.$article_id.'/accept');
        $newsPayload->setReferer('/news/'.$article_id.'/update');
        $newsPayload->removeFormParam('draft');
        $newsPayload->removeFormParam('returnUrl');
        $publishFromDraftRequest = $newsPayload->payloadToArray();

        $response = $i->sendCurlRequest($publishFromDraftRequest['method'], $publishFromDraftRequest['page'], $publishFromDraftRequest['payload'], true, 'Send request to publish draft news');
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/news/'.$article_id=>'302'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Accept news in moderate using curl request
     *
     *  Fires off a create step and putting the news into moderation
     *
     * @param IntegrationTester $i
     * @param NewsPayload       $newsPayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function publishNewsFromModerate(IntegrationTester $i, NewsPayload $newsPayload){
        #GIVEN
        $i->loginAdmin2();
        $timestamp=time();
        $title = 'Smoke Test News Title Publish Form Moderate at '.$timestamp;

        $newsPayload->setNewsTitle($title);
        $createDraftRequest = $newsPayload->payloadToArray();
        $i->sendCurlRequest($createDraftRequest['method'], $createDraftRequest['page'], $createDraftRequest['payload'], false, 'Send request to create draft news');
        $article_id=$i->getNewsArticleByTitle($title)->getId();

        $newsPayload->setPage('/news/moderation/'.$article_id.'/submit');
        $newsPayload->setReferer('/news/'.$article_id.'/update');
        $newsPayload->removeFormParam('draft');
        $newsPayload->removeFormParam('returnUrl');
        $moderateDraftRequest = $newsPayload->payloadToArray();
        $i->sendCurlRequest($moderateDraftRequest['method'], $moderateDraftRequest['page'], $moderateDraftRequest['payload'], false, 'Send request to publish draft news to moderate');

        #WHEN
        $newsPayload->setPage('/news/moderation/'.$article_id.'/accept');
        $newsPayload->setReferer('/news/'.$article_id.'/update');
        $newsPayload->clearAllFormParams();
        $newsPayload->setFormParam('articleID', $article_id);
        $moderateAcceptRequest = $newsPayload->payloadToArray();
        $response = $i->sendCurlRequest($moderateAcceptRequest['method'], $moderateAcceptRequest['page'], $moderateAcceptRequest['payload'], true, 'Send request to accept news from moderate');
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/news/'.$article_id=>'302'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];
        #THEN
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Reject news in moderate using curl request
     *
     *  Fires off a create step and putting the news into moderation
     *
     * @param IntegrationTester $i
     * @param NewsPayload       $newsPayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function rejectNewsFromModerate(IntegrationTester $i, NewsPayload $newsPayload){
        #GIVEN
        $i->loginAdmin2();
        $timestamp=time();
        $title = 'Smoke Test News Title Reject from Moderate at '.$timestamp;

        $newsPayload->setNewsTitle($title);
        $createDraftRequest = $newsPayload->payloadToArray();
        $i->sendCurlRequest($createDraftRequest['method'], $createDraftRequest['page'], $createDraftRequest['payload'], false, 'Send request to create draft news');
        $article_id=$i->getNewsArticleByTitle($title)->getId();

        $newsPayload->setPage('/news/moderation/'.$article_id.'/submit');
        $newsPayload->setReferer('/news/'.$article_id.'/update');
        $newsPayload->removeFormParam('draft');
        $newsPayload->removeFormParam('returnUrl');
        $moderateDraftRequest = $newsPayload->payloadToArray();
        $i->sendCurlRequest($moderateDraftRequest['method'], $moderateDraftRequest['page'], $moderateDraftRequest['payload'], false, 'Send request to publish draft news to moderate');

        #WHEN
        $newsPayload->setPage('/news/moderation/'.$article_id.'/reject');
        $newsPayload->setReferer('/news/'.$article_id.'/update');
        $newsPayload->clearAllFormParams();
        $newsPayload->setFormParam('articleID', $article_id);
        $newsPayload->setFormParam('rejectReason', 'The reason is valid');
        $moderateRejectRequest = $newsPayload->payloadToArray();
        $response = $i->sendCurlRequest($moderateRejectRequest['method'], $moderateRejectRequest['page'], $moderateRejectRequest['payload'], true, 'Send request to reject news from moderate');
        $expectedUrlsReturned= [[GuzzleConstants::NO_REDIRECT=>'200']];

        #THEN;
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Save news for later using curl request
     *
     *  Fires off a create step and publish step for news.
     *
     * @param IntegrationTester $i
     * @param NewsPayload       $newsPayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function saveNewsForLater(IntegrationTester $i, NewsPayload $newsPayload){
        #GIVEN
        $i->loginAdmin2();
        $timestamp=time();
        $title = 'Smoke Test News Title Save news for later at '.$timestamp;

        $newsPayload->setNewsTitle($title);
        $createDraftRequest = $newsPayload->payloadToArray();
        $i->sendCurlRequest($createDraftRequest['method'], $createDraftRequest['page'], $createDraftRequest['payload'], false, 'Send request to create draft news');
        $article_id=$i->getNewsArticleByTitle($title)->getId();

        $newsPayload->setPage('/news/moderation/'.$article_id.'/accept');
        $newsPayload->setReferer('/news/'.$article_id.'/update');
        $newsPayload->removeFormParam('draft');
        $newsPayload->removeFormParam('returnUrl');
        $publishFromDraftRequest = $newsPayload->payloadToArray();
        $i->sendCurlRequest($publishFromDraftRequest['method'], $publishFromDraftRequest['page'], $publishFromDraftRequest['payload'], false, 'Send request to publish draft news');

        #WHEN
        $newsPayload->setPage('/news/saved/'.$article_id.'/add');
        $newsPayload->setReferer('/news');
        $newsPayload->clearAllFormParams();
        $saveNewsForLaterRequest= $newsPayload->payloadToArray();
        $response = $i->sendCurlRequest($saveNewsForLaterRequest['method'], $saveNewsForLaterRequest['page'], $saveNewsForLaterRequest['payload'], true, 'Send request to save news for later');
        $expectedUrlsReturned= [[GuzzleConstants::NO_REDIRECT=>'200']];
        #THEN;
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }

    /** Delete news using curl request
     *
     *  Fires off a create step and publish step for news.
     *
     * @param IntegrationTester $i
     * @param NewsPayload       $newsPayload
     * @throws \Schoolbox\External\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function deleteNews(IntegrationTester $i, NewsPayload $newsPayload)
    {
        #GIVEN
        $i->loginAdmin2();
        $timestamp = time();
        $title = 'Smoke Test News Title Delete, I should not exist. at ' . $timestamp;

        $newsPayload->setNewsTitle($title);
        $createDraftRequest = $newsPayload->payloadToArray();
        $i->sendCurlRequest($createDraftRequest['method'], $createDraftRequest['page'], $createDraftRequest['payload'], false, 'Send request to create draft news');
        $article_id = $i->getNewsArticleByTitle($title)->getId();

        $newsPayload->setPage('/news/moderation/' . $article_id . '/accept');
        $newsPayload->setReferer('/news/' . $article_id . '/update');
        $newsPayload->removeFormParam('draft');
        $newsPayload->removeFormParam('returnUrl');
        $publishFromDraftRequest = $newsPayload->payloadToArray();
        $i->sendCurlRequest($publishFromDraftRequest['method'], $publishFromDraftRequest['page'], $publishFromDraftRequest['payload'], false, 'Send request to publish draft news');

        #WHEN
        $newsPayload->setPage('/news/' . $article_id . '/delete');
        $newsPayload->setReferer('/news/' . $article_id . '/update');
        $newsPayload->clearAllFormParams();
        $newsPayload->setFormParam('articleID', $article_id);
        $deleteNewsRequest = $newsPayload->payloadToArray();
        $response = $i->sendCurlRequest($deleteNewsRequest['method'], $deleteNewsRequest['page'], $deleteNewsRequest['payload'], true, 'Send request to delete news');
        $expectedUrlsReturned= [
            ['https://vagrant.dev.schoolbox.cloud/news'=>'302'],
            [GuzzleConstants::NO_REDIRECT=>'200']
        ];

        #THEN;
        $i->checkCurlReturn($expectedUrlsReturned, $response);
    }
}