<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/**
 * Class SubmissionHistoryCest
 */

namespace News;

use AcceptanceTester;
use Exception;
use Page\News\Form;
use Page\News\Index;
use Page\News\Item;

class CreateAndVerifyCest
{
    /**
     * Test that a news item I create retains the settings I chose
     *
     * as alaress:
     * * go to http://192.168.36.10/news
     * * create a new news item, with:
     * ** headline
     * ** article
     * ** blurb
     * ** feature image
     * ** topic
     * ** limit its audience to teachers
     * * save it
     * * verify that:
     * ** it shows in the All topic
     * ** it shows in the topic I put it in
     * ** it doesn't show in other topics
     * ** its blurb (not article) shows in the topic view
     * ** its headline matches what I entered
     * ** in the article view, its article matches what I entered
     * ** when I modify it, everything I submitted is there
     *

    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * get logged in
     */
    public function _before(AcceptanceTester $i)
    {
        $i->logout();
        $i->login('alaress', 'alaress');
        $formPage = new Form($i);
        $i->amOnPage($formPage->generateCreateRoute());
    }

    /**
     * Deleting news article when the test is completed
     *
     * @param AcceptanceTester $i
     */
    public function _after(AcceptanceTester $i): void
    {
        $i->amGoingTo("update delete_at for the news article so it does not impact the news tests");
        $i->updateInDatabase('comms_news_article', ['deleted_at' => "2020-01-15 11:22:34"], ['title like' => "Acceptance Test News Article Testing%"]);
    }

    /**
     * @param AcceptanceTester $i
     * @param Form             $formPage
     * @param Index            $indexPage
     * @param Item             $itemPage
     */
    public function createNews(AcceptanceTester $i, Form $formPage, Index $indexPage, Item $itemPage)
    {
        $i->amGoingTo("Fill in form values");
        $timestamp = time();
        $headline     = "Acceptance Test News Article Testing " . $timestamp;
        $article = "<h2>Random Article</h2> <p>Random article text</p>";
        $blurb     = "<p>Blurb</p>";
        $image     = __DIR__ . '/upload/test.jpg';
        $topic     = 'General';
        $topicSlug = (new \Url($topic, ''))->getSlug();
        $roles     = ['Teacher'];
        $campi     = ['Primary'];
        $users     = ['Jana Reed','Mario Izzo'];
        $publish   = \Alaress_Date_Format::now()->addHours(-1)->setTime(0, 0, 0);
        $expiry    = \Alaress_Date_Format::now()->addDays(7)->setTime(0, 0, 0);
        $formPage
            ->fillHeadline($headline)
            ->fillArticle($article)
            ->fillBlurb($blurb)
            ->fillFeatureImage($image)
            ->fillTopic($topic)
            ->fillRoles($roles)
            ->fillCampuses($campi)
            ->fillUsers($users)
            ->fillPublishDate($publish)
            ->fillExpiryDate($expiry)
            ->submit();

        $i->amGoingTo("Verify that the news is in the All topic, and is the first news item");
        sleep(5);
        $i->amOnPage($indexPage->generateRoute('all'));

        $position = 1;
        $indexPage->seeHeadline($headline, $position);

        $i->amGoingTo("Verify that the blurb shows, not the article I entered");
        $indexPage->seeArticleSummary($blurb, $position);
        $indexPage->dontSeeArticleSummary($article, $position);
        $i->amGoingTo("the news article has a feature image");
        $featureImage = $i->grabFromDatabase('comms_news_article', 'feature_image_hash', ['title' => $headline]);
        $i->assertNotNull($featureImage);

        $i->amGoingTo("Verify I don't see it at all in some other topic");
        $i->amOnPage($indexPage->generateRoute('music'));
        $indexPage->dontSeeHeadline($headline, $position);

        $i->amGoingTo("Verify I see it in the topic I put it in");
        // (URL has invisible dependency on config; all I want is a slug)
        $i->amOnPage($indexPage->generateRoute($topicSlug));
        $indexPage->seeHeadline($headline, $position);

        $i->amGoingTo("View the article I'm searched for");
        $indexPage->viewArticle($position);

        $i->amGoingTo("Verify I see the article, and not the blurb");
        $itemPage->seeHeadline($headline);
        $itemPage->seeArticle($article);
        $itemPage->modifyArticle();

        $i->amGoingTo("Now, I'm back on the form page. Verify what is there is what I entered");
        $formPage
            ->seeHeadline($headline)
            ->seeArticle($article)
            ->seeBlurb($blurb)
            ->seeFeatureImage($image)
            ->seeTopic($topic)
            ->seeRoles($roles)
            ->seeCampuses($campi)
            ->seeUsers($users)
            ->seePublishDate($publish)
            ->seeExpiryDate($expiry);

        $i->amGoingTo("Verify that user Tim Reed(student from secondary) cannot see it");
        $tim = $i->haveFriend('tim');
        $tim->does(function (AcceptanceTester $i) use ($headline, $topicSlug) {
            $i->login('treed', 'password');
            $indexPage = new Index($i);

            $i->amGoingTo("Go to all page");
            $i->amOnPage($indexPage->generateRoute('all'));
            $position = 1;
            $indexPage->dontSeeHeadline($headline, $position);

            $i->amOnPage($indexPage->generateRoute($topicSlug));
            $indexPage->dontSeeHeadline($headline, $position);
        });

        $i->amGoingTo("Verify that user Alison Chan(teacher) can see it");
        $alison = $i->haveFriend('alison');
        $alison->does(function (AcceptanceTester $i) use ($headline, $topicSlug) {
            $i->login('achan', 'password');
            $indexPage = new Index($i);

            $i->amGoingTo("Go to all page");
            $i->amOnPage($indexPage->generateRoute('all'));
            $position = 1;
            $indexPage->seeHeadline($headline, $position);

            $i->amOnPage($indexPage->generateRoute($topicSlug));
            $indexPage->seeHeadline($headline, $position);
        });
        $i->amGoingTo("Make it unpublished");
        $formPage
            ->fillPublishDate(\Alaress_Date_Format::now()->addDays(2)->setTime(0, 0, 0))
            ->submit();

        $i->amGoingTo("Verify that user Alison Chan can no longer see it");
        $alison->does(function (AcceptanceTester $i) use ($headline, $topicSlug) {
            $indexPage = new Index($i);

            $i->amGoingTo("Go to all page");
            $i->amOnPage($indexPage->generateRoute('all'));
            $position = 1;
            $indexPage->dontSeeHeadline($headline, $position);

            $i->amOnPage($indexPage->generateRoute($topicSlug));
            $indexPage->dontSeeHeadline($headline, $position);
        });
    }

    /**
     * @param AcceptanceTester $i
     * @param Form             $formPage
     * @param Index            $indexPage
     * @param Item             $itemPage
     */
    public function newsAudienceTest(AcceptanceTester $i, Form $formPage, Index $indexPage, Item $itemPage)
    {
        $i->amGoingTo("Fill in form values with composite audience: 4 roles and 2 campuses + team and role");
        $timestamp = time();
        $headline     = "Acceptance Test News Audience Article Testing " . $timestamp;
//        $article = "<h2>Random Article</h2> <p>Random article text</p>";
//        $blurb     = "<p>Blurb</p>";
//        $image     = __DIR__ . '/upload/test.jpg';
//        $topic     = 'General';
//        $topicSlug = (new \Url($topic, ''))->getSlug();
        $roles     = ['Teacher'];
        $audienceType1     = 'Team';
        $audienceType2     = 'Year Level';
        $checkbox1 = 'primary-IjEi';
        $checkbox2 = 'secondary-Ijki';
//        $campi     = ['Primary','Secondary'];
//        $users     = ['Jana Reed','Mario Izzo'];
        $publish   = \Alaress_Date_Format::now()->addHours(-1)->setTime(0, 0, 0);
        $expiry    = \Alaress_Date_Format::now()->addDays(7)->setTime(0, 0, 0);
        $formPage
            ->fillHeadline($headline)
//            ->fillArticle($article)
//            ->fillBlurb($blurb)
//            ->fillFeatureImage($image)
//            ->fillTopic($topic)
            ->createCompositeAudience($audienceType1, $audienceType2, $checkbox1, $checkbox2)
//            ->fillCampuses($campi)
//            ->fillUsers($users)
            ->fillPublishDate($publish)
            ->fillExpiryDate($expiry);
    }
}
