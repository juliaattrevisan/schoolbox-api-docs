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
use Codeception\Util\Locator;
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
        //$i->updateInDatabase('comms_news_article', ['deleted_at' => "2020-01-15 11:22:34"], ['title like' => "Acceptance Test News%"]);
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
        $i->amGoingTo("Fill in form values with composite audience: 4 roles and 2 campuses + team and year level");
        $timestamp = time();
        $headline     = "Acceptance Test News Composite Audience Article Testing " . $timestamp;
        $publish   = \Alaress_Date_Format::now()->addHours(-1)->setTime(0, 0, 0);
        $expiry    = \Alaress_Date_Format::now()->addDays(7)->setTime(0, 0, 0);
        $formPage
            ->fillHeadline($headline)
            ->createCompositeAudience('Team', 'Year Level', ['primary-IjEi', 'secondary-Ijki']);
        $formPage->seeRoles(['1 Team & 1 Year Level']);
        $formPage
            ->createCompositeAudience('Role', 'Campus', ['primary-IjEi','primary-IjIi','primary-Ijci', 'primary-IjQi', 'secondary-IjEi','secondary-IjIi']);
        $formPage->seeRoles(['2 Campuses & 4 Roles']);
        $i->amGoingTo('check the draft article presents the proper audience: 4 roles and 2 campuses + 1 team and 1 year level');
        $i->amOnPage('/news/me?status=Drafted');
        $element = Locator::contains('div.small-12.card h3 a', 'Acceptance Test News Composite Audience Article Testing');
        $i->click($element);
        $i->canSee('2 Campuses & 4 Roles');
        $i->canSee('1 Team & 1 Year Level');
        $i->amGoingTo('check the modify article page presents the proper audience: 4 roles and 2 campuses + 1 team and 1 year level');
        $itemPage->modifyArticle();
        $formPage->seeRoles(['2 Campuses & 4 Roles']);
        $formPage->seeRoles(['1 Team & 1 Year Level']);

        $i->amGoingTo("Fill in form values with selecting audience: campus Primary + class 9A Eng (group members and parents)");
        $formPage->removeAudience(['2 Campuses & 4 Roles','1 Team & 1 Year Level']);
        $formPage->selectAudienceOption('Campuses', 'Primary', []);
        $formPage->selectAudienceOption('Classes & Groups', '9A English (9ENGA)', ['audience-select-parents']);
        $i->amGoingTo('check the modify article page presents the proper audience: Primary and 9A English (9ENGA) Inc parents of students');
        $formPage->seeRoles(['Primary']);
        $formPage->seeRoles(['9A English (9ENGA)']);
        $i->canSee('Class/Group');
        $i->canSee('(Inc. parents of students)');
        $i->amGoingTo('check the draft article presents the proper audience: Primary and 9A English (9ENGA) Inc parents of students');
        $i->amOnPage('/news/me?status=Drafted');
        $element = Locator::contains('div.small-12.card h3 a', 'Acceptance Test News Composite Audience Article Testing');
        $i->click($element);
        $i->canSee('9A English (9ENGA) (Inc. parents of students)');
        $i->canSee('(Inc. parents of students)');

        $i->amGoingTo('Fill in form values by typing an audience: Jana Reed, Tim Reed and Jenni Reed');
        $itemPage->modifyArticle();
        $formPage->removeAudience(['9A English (9ENGA)','Primary']);
        $formPage->fillUsers(['Jenni','Jana','Tim','Demo 1','Primary']);
        $i->canSee('Jana Reed (Middle Student)');
        $i->canSee('Tim Reed (Senior Student)');
        $i->canSee('Jenni Reed (Parent)');
        $i->canSee('Demo Class Page 1 (DEMO1)');
        $i->canSee('Primary');
        $i->amGoingTo('check the draft article presents the proper audience: Primary and Demo class 1');
        $i->amOnPage('/news/me?status=Drafted');
        $element = Locator::contains('div.small-12.card h3 a', 'Acceptance Test News Composite Audience Article Testing');
        $i->click($element);
        $i->canSee('Demo Class Page 1 (DEMO1)');
        $i->canSee('Primary');

        $i->amGoingTo('Fill in form values by selecting audience via bricks and composite: year level + house and Teams Raiders');
        $itemPage->modifyArticle();
        $formPage->removeAudience(['Jana Reed','Tim Reed','Jenni Reed','Primary','Demo Class Page 1 (DEMO1)']);
        $formPage->selectAudienceOption('Year Levels', 'Year 9', ['audience-select-parents']);
        $formPage->createCompositeAudience('House', 'Team', ['primary-IjEi','secondary-IjIi']);
        $i->canSee('1 Team & 1 House');
        $i->canSee('Year 9');
        $i->canSee('Composite Audience (view details)');
        $i->canSee('Year Level');
        $i->canSee('Inc. parents of students');
        $i->amGoingTo('check the draft article presents the proper audience: Primary and Demo class 1');
        $i->amOnPage('/news/me?status=Drafted');
        $element = Locator::contains('div.small-12.card h3 a', 'Acceptance Test News Composite Audience Article Testing');
        $i->click($element);
        $i->canSee('1 Team & 1 House');
        $i->canSee('Year 9');
        $i->canSee('Inc. parents of students');
        $i->amGoingTo('Publish the news article and ensure the audience remains the same');
        $itemPage->modifyArticle();
        $formPage->submit();
        $i->canSee('1 Team & 1 House');
        $i->canSee('Year 9');
        $i->canSee('Inc. parents of students');
    }
}
