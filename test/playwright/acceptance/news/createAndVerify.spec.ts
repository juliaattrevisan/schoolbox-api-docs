import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import Form from "@schoolboxTest/playwright/support/page/news/Form";
import Index from "@schoolboxTest/playwright/support/page/news/Index";
import Item from "@schoolboxTest/playwright/support/page/news/Item";
import path from "path";
import ManageGroup from "@schoolboxTest/playwright/support/page/group/ManageGroup";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

/**
 * Convenience function to create a folder
 * @param tester - The tester
 * @param page - The page
 * @param db - The database helper
 */
async function createNewsFolder(tester: AcceptanceTester, page: Page, db: DatabaseHelper): Promise<void> {
    const createFolder = new CreateFolder(page);
    const manageGroup = new ManageGroup(page);

    const folderName = "News Folder Test";
    const homepageTemplateOption = "Course";

    tester.isGoingTo("Create a folder page to create articles for that audience");
    await createFolder.goto();
    await createFolder.typeName(folderName);
    await createFolder.setHomepageTemplate(homepageTemplateOption);
    await createFolder.setAccessPermission(1, "write");
    await createFolder.setAccessPermission(2, "write");
    tester.isGoingTo("give senior student write permission");
    await createFolder.setAccessPermission(3, "write");
    await createFolder.setAccessPermission(4, "write");
    tester.isGoingTo("give junior student write permission");
    await createFolder.setAccessPermission(5, "read");
    tester.isGoingTo("give middle student none permission");
    await createFolder.setAccessPermission(6, "none");
    await createFolder.clickCreateGroupToggle();
    await createFolder.submit();
    const folderId = (await db.grabFromDatabase("folder", "id", {
        deleted_at: null,
        "name LIKE": folderName,
    })) as number;
    expect(folderId).not.toBe(null);

    await manageGroup.setAccessPermission("type-3");
    await manageGroup.clickUpdateButton();
    await page.goto(`/homepage/${folderId}`);
}

test.describe("Create and Verify News Article Test", () => {
    /**
     * Preparations
     *
     * get logged in as admin and navigiating to create news page
     */
    test.beforeEach(async ({ tester, page }) => {
        await tester.logout();
        await tester.loginAsAdmin();
        const form = new Form(tester, page);
        tester.isGoingTo("news form page");
        await form.goto();
    });

    /**
     * Deleting data when the test is about to start so it does not get impacted by other tests already executed
     * @param baseTester
     * @param db
     */
    test.beforeEach(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update delete_at for the news article so it does not impact the news tests");
        await db.updateInDatabase(
            "comms_news_article",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Acceptance Test News%" },
        );
    });

    /**
     * as alaress:
     * go to http://192.168.36.10/news
     * create a new news item, with:
     * headline
     * article
     * blurb
     * feature image
     * topic
     * limit its audience to teachers
     * save it
     * verify that:
     * it shows in the All topic
     * it shows in the topic I put it in
     * it doesn't show in other topics
     * its blurb (not article) shows in the topic view
     * its headline matches what I entered
     * in the article view, its article matches what I entered
     * when I modify it, everything I submitted is there
     *
     */

    test("Create news article", async ({ tester, page, db }) => {
        const form = new Form(tester, page);
        const index = new Index(tester, page);
        const item = new Item(tester, page);
        const headline = "Acceptance Test News Article Testing ";
        const article = "Random Article Random article text";
        const blurb = "Blurb";
        const image = path.join(__dirname, "../../support/file/baboon.png");
        const topic = "General";
        const role = "Teacher";
        const campus = "Primary";
        const user1 = "Jana Reed";
        const user2 = "Mario Izzo";
        const student = "Mr Tim Reed";
        const teacher = "Ms Alison Chan";
        tester.isGoingTo("Fill news article form");
        await form.fillHeadline(headline);
        await form.fillArticle(article);
        await form.fillBlurb(blurb);
        await form.attachFeatureImage(image);
        await form.fillTopic(topic);
        await form.selectAudienceOption("Roles");
        await form.checkboxAudience(role);
        await form.fillUser(user1);
        await form.fillUser(user2);
        const publish = new Date();
        publish.setDate(publish.getDate() - 2); // 2 days ago
        const expiry = new Date();
        expiry.setDate(expiry.getDate() + 7); // 7 days from now
        await form.fillPublishDate(publish);
        await form.fillExpiryDate(expiry);
        await form.selectAudienceOption("Campuses");
        await form.checkboxAudience(campus);
        await form.submit();
        // had to add this as it was going away from this page too quickly and not publishing
        await page.waitForURL("/");

        tester.isGoingTo("Verify that the news is in the All topic, and is the first news item");
        await index.goto("all");

        await index.seeHeadline(headline);

        tester.isGoingTo("Verify that the blurb shows, not the article I entered");
        await index.seeArticleSummary(blurb);
        await index.dontSeeArticleSummary(article);

        tester.isGoingTo("the news article has a feature image");
        const featureImage = (await db.grabFromDatabase("comms_news_article", "feature_image_hash", {
            title: headline,
            deleted_at: null,
        })) as string;

        tester.isGoingTo("Verify I don't see it at all in some other topic");
        await index.goto("music");
        await index.dontSeeHeadline(headline);

        tester.isGoingTo("Verify I see it in the topic I put it in");
        await index.goto("general");
        await index.seeHeadline(headline);

        tester.isGoingTo("View the article I'm searched for");
        const articleId = (await db.grabFromDatabase("comms_news_article", "id", {
            title: headline,
            deleted_at: null,
        })) as number;
        await index.viewArticle(articleId);

        tester.isGoingTo("Verify I see the article, and not the blurb");
        await item.seeHeadline(headline);
        await item.seeArticle(article);
        // added this timeout as it was clicking the 3-dot button too early
        await page.waitForTimeout(1000);
        await item.modifyArticle();

        tester.isGoingTo("Now, I'm back on the form page. Verify what is there is what I entered");
        await form.seeHeadline(headline);
        await form.seeArticle(article);
        await form.seeBlurb(blurb);
        await form.seeFeatureImage("baboon", featureImage);
        await form.seeTopic(topic);
        await form.seeAudience(role);
        await form.seeAudience(campus);
        await form.seeUser(user1);
        await form.seeUser(user2);
        // await form.seePublishDate(publish);
        // await form.seeExpiryDate(expiry);

        tester.isGoingTo("Verify that user Tim Reed(student from secondary) cannot see it");
        await tester.impersonateViaModifyUserPage(student);
        await index.goto("all");
        await index.dontSeeHeadline(headline);
        await index.goto("general");
        await index.dontSeeHeadline(headline);

        tester.isGoingTo("Verify that user Alison Chan(teacher) can see it");
        await tester.impersonateViaModifyUserPage(teacher);
        await index.goto("all");
        await index.seeHeadline(headline);
        await index.goto("general");
        await index.seeHeadline(headline);

        tester.isGoingTo("Make it unpublished");
        await tester.loginAsAdmin();
        await index.goto("all");
        await index.viewArticle(articleId);
        await item.modifyArticle();
        publish.setDate(publish.getDate() + 2); // 2 days from now
        await form.fillPublishDate(publish);
        await form.submit();

        tester.isGoingTo("Verify that user Alison Chan can no longer see it");
        await tester.impersonateViaModifyUserPage(teacher);
        await index.goto("all");
        await index.dontSeeHeadline(headline);
        await index.goto("general");
        await index.dontSeeHeadline(headline);
    });

    /**
     * creates a news article via news/create
     * check the draft is properly saved with the proper audience
     * modify the audience a couple of times ensure it is properly updated
     * publish the article and ensure the proper audience is presented
     *
     */

    // test("News Audience Test", async ({ tester, page }) => {
    //     const form = new Form(tester, page);
    //     const index = new Index(tester, page);
    //     const item = new Item(tester, page);
    //     tester.isGoingTo("Fill in form values with composite audience: 4 roles and 2 campuses + team and year level");
    //     const headline = "Acceptance Test News Composite Audience Article Testing ";
    //     // await tester.impersonateViaModifyUserPage("Mr Jack Coles");
    //     await form.goto();
    //     await form.fillHeadline(headline);
    //     // await form.createCompositeAudience("Team", ["primary-IjEi"], "Year Level", ["secondary-Ijki"]);
    //     // await form.seeAudience("1 Team & 1 Year Level");

    //     await form.createCompositeAudience("Campus", ["primary-IjEi", "primary-IjIi"], "Role", [
    //         "secondary-IjEi",
    //         "secondary-IjIi",
    //         "secondary-IjQi",
    //         "secondary-Ijci",
    //     ]);
    //     // await form.seeAudience("2 Campuses & 4 Roles");
    // });

    /**
     * creates a news article via news component in a class page
     * check the draft is properly saved with the proper audience
     * modify the audience a couple of times ensure it is properly updated
     * publish the article and ensure the proper audience is presented
     */

    test("News Audience Via Class Page Test", async ({ tester, page, db }) => {
        const form = new Form(tester, page);
        const index = new Index(tester, page);
        const item = new Item(tester, page);
        const homepage = new Homepage(page);

        tester.isGoingTo("Create a folder page to create articles for that audience");
        await createNewsFolder(tester, page, db);

        tester.isGoingTo("Add a News component into the page");
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_INFORMATION, "News");
        tester.isGoingTo("Enter content into the new component");
        const layout = Homepage.ELEMENT_COMPONENT_LAYOUT_TOP;
        const position = 1;
        await homepage.clickComponentEmptyStateLink(layout, position);
        const headline = "Acceptance Test News Audience Article via Class Page Testing ";
        await form.fillHeadline(headline);
        await form.fillArticle("Random Article");
        await form.seeAudience("News Folder Test");
        // waiting to make sure the drat is created
        await expect(page.locator(Form.FORM_SAVE)).toBeEnabled();

        tester.isGoingTo("Check the draft article presents the proper audience: News Folders Test");
        await page.goto("/news/me?status=Drafted");
        const articleId = (await db.grabFromDatabase("comms_news_article", "id", {
            title: headline,
            deleted_at: null,
        })) as number;
        await index.clickArticleTitle(articleId);
        tester.isGoingTo("click the news article");
        await page.goto(`/news/${articleId}`);
        await item.seeArticleAudience("News Folder Test");

        tester.isGoingTo("check the modify article page presents the proper audience: News Folders Test");
        await item.modifyArticle();
        await form.seeAudience("News Folder Test");

        tester.isGoingTo("Add new audience to the news article: Eden Reed and Teacher role");
        await form.selectAudienceOption("Roles");
        await form.checkboxAudience("Teacher");
        await page.waitForTimeout(1000);
        await form.fillUser("Eden Reed");
        // this is the time it takes to save a draft currently
        await page.waitForTimeout(3000);

        tester.isGoingTo("Check the audience is properly presented: News Folders, Eden Reed and Teacher");
        await form.seeAudience("News Folder Test");
        await form.seeAudience("Teacher");
        await form.seeAudience("Ms Eden Reed (506) (Junior Student)");

        tester.isGoingTo("check the draft article presents the proper audience: News Folders and Teacher");
        await page.goto("/news/me?status=Drafted");
        await index.clickArticleTitle(articleId);
        await item.seeArticleAudience("News Folder Test");
        await item.seeArticleAudience("Teacher");

        tester.isGoingTo(
            "check the modify article page presents the proper audience: News Folders, Eden Reed and Teacher",
        );
        await item.modifyArticle();
        await form.seeAudience("News Folder Test");
        await form.seeAudience("Ms Eden Reed (506) (Junior Student)");
        await form.seeAudience("Teacher");
        await form.submit();
        await item.seeArticleStatus("Published");
        await item.seeArticleAudience("News Folder Test");
        await item.seeArticleAudience("Teacher");
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update delete_at for the news article so it does not impact the news tests");
        await db.updateInDatabase(
            "comms_news_article",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Acceptance Test News%", deleted_at: null },
        );
    });
});

// <?php

// /**
//  * @copyright Copyright 2019, Schoolbox Pty Ltd
//  * @link      https://schoolbox.com.au/
//  */

// /**
//  * Class SubmissionHistoryCest
//  */

// namespace Tests\Acceptance\News;

// use Tests\Support\AcceptanceTester;
// use Codeception\Util\Locator;
// use Exception;
// use Tests\Support\Page\Homepage;
// use Tests\Support\Page\Folder\CreateFolder;
// use Tests\Support\Page\Group\ManageGroup;
// use Tests\Support\Page\News\Form;
// use Tests\Support\Page\News\Index;
// use Tests\Support\Page\News\Item;
// use Schoolbox\User;

// /**
//  *
//  * Test that a news item I create retains the settings I chose
//  * creates a news article and checks the contents is properly recorded, ensure the proper audience can view the article
//  *
//  * Steps:
//  * 1. Provision legacy.tar.gz data
//  * 2. vagrant ssh
//  * 3. Execute cd /usr/share/schoolbox && ./vendor/codeception/codeception/codecept --steps run Acceptance News/CreateAndVerifyCest
//  *
//  */

// class CreateAndVerifyCest
// {
//     /**
//      * Preparations
//      *
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * get logged in
//      */
//     public function _before(AcceptanceTester $i): void
//     {
//         $i->logout();
//         $i->login('alaress', 'alaress');
//         $formPage = new Form($i);
//         $i->amOnPage($formPage->generateCreateRoute());
//     }

//     /**
//      * Deleting news article when the test is completed
//      *
//      * @param AcceptanceTester $i
//      */
//     public function _after(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("update delete_at for the news article so it does not impact the news tests");
//         $i->updateInDatabase('comms_news_article', ['deleted_at' => "2020-01-15 11:22:34"], ['title like' => "Acceptance Test News%"]);
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @param Form             $formPage
//      * @param Index            $indexPage
//      * @param Item             $itemPage
//      * @throws Exception
//      *
//      * as alaress:
//      * go to http://192.168.36.10/news
//      * create a new news item, with:
//      ** headline
//      ** article
//      ** blurb
//      ** feature image
//      ** topic
//      ** limit its audience to teachers
//      * save it
//      * verify that:
//      ** it shows in the All topic
//      ** it shows in the topic I put it in
//      ** it doesn't show in other topics
//      ** its blurb (not article) shows in the topic view
//      ** its headline matches what I entered
//      ** in the article view, its article matches what I entered
//      ** when I modify it, everything I submitted is there
//      *
//      */
//     public function createNews(AcceptanceTester $i, Form $formPage, Index $indexPage, Item $itemPage): void
//     {
//         $i->amGoingTo("Fill in form values");
//         $timestamp = time();
//         $headline     = "Acceptance Test News Article Testing " . $timestamp;
//         $article = "<h2>Random Article</h2><p>Random article text</p>";
//         $blurb     = "<p>Blurb</p>";
//         $image     = __DIR__ . '/upload/test.jpg';
//         $topic     = 'General';
//         $topicSlug = (new \Url($topic, ''))->getSlug();
//         $roles     = ['Teacher'];
//         $campi     = ['Primary'];
//         $users     = ['Jana Reed','Mario Izzo'];
//         $publish   = \Alaress_Date_Format::now()->addHours(-1)->setTime(0, 0, 0);
//         $expiry    = \Alaress_Date_Format::now()->addDays(7)->setTime(0, 0, 0);
//         $formPage
//             ->fillHeadline($headline)
//             ->fillArticle($article)
//             ->fillBlurb($blurb)
//             ->fillFeatureImage($image)
//             ->fillTopic($topic)
//             ->fillRoles($roles)
//             ->fillCampuses($campi)
//             ->fillUsers($users)
//             ->fillPublishDate($publish)
//             ->fillExpiryDate($expiry)
//             ->submit();

//         $i->amGoingTo("Verify that the news is in the All topic, and is the first news item");
//         sleep(5);
//         $i->amOnPage($indexPage->generateRoute('all'));

//         $position = 1;
//         $indexPage->seeHeadline($headline, $position);

//         $i->amGoingTo("Verify that the blurb shows, not the article I entered");
//         $indexPage->seeArticleSummary($blurb, $position);
//         $indexPage->dontSeeArticleSummary($article, $position);
//         $i->amGoingTo("the news article has a feature image");
//         $featureImage = $i->grabFromDatabase('comms_news_article', 'feature_image_hash', ['title' => $headline]);
//         $i->assertNotNull($featureImage);

//         $i->amGoingTo("Verify I don't see it at all in some other topic");
//         $i->amOnPage($indexPage->generateRoute('music'));
//         $indexPage->dontSeeHeadline($headline, $position);

//         $i->amGoingTo("Verify I see it in the topic I put it in");
//         // (URL has invisible dependency on config; all I want is a slug)
//         $i->amOnPage($indexPage->generateRoute($topicSlug));
//         $indexPage->seeHeadline($headline, $position);

//         $i->amGoingTo("View the article I'm searched for");
//         $indexPage->viewArticle($position);

//         $i->amGoingTo("Verify I see the article, and not the blurb");
//         $itemPage->seeHeadline($headline);
//         $itemPage->seeArticle($article);
//         $itemPage->modifyArticle();

//         $i->amGoingTo("Now, I'm back on the form page. Verify what is there is what I entered");
//         $formPage
//             ->seeHeadline($headline)
//             ->seeArticle($article)
//             ->seeBlurb($blurb)
//             ->seeFeatureImage($image)
//             ->seeTopic($topic)
//             ->seeRoles($roles)
//             ->seeCampuses($campi)
//             ->seeUsers($users)
//             ->seePublishDate($publish)
//             ->seeExpiryDate($expiry);

//         $i->amGoingTo("Verify that user Tim Reed(student from secondary) cannot see it");
//         $tim = $i->haveFriend('tim');
//         $tim->does(function (AcceptanceTester $i) use ($headline, $topicSlug) {
//             $i->login('treed', 'password');
//             $indexPage = new Index($i);

//             $i->amGoingTo("Go to all page");
//             $i->amOnPage($indexPage->generateRoute('all'));
//             $position = 1;
//             $indexPage->dontSeeHeadline($headline, $position);

//             $i->amOnPage($indexPage->generateRoute($topicSlug));
//             $indexPage->dontSeeHeadline($headline, $position);
//         });

//         $i->amGoingTo("Verify that user Alison Chan(teacher) can see it");
//         $alison = $i->haveFriend('alison');
//         $alison->does(function (AcceptanceTester $i) use ($headline, $topicSlug) {
//             $i->login('achan', 'password');
//             $indexPage = new Index($i);

//             $i->amGoingTo("Go to all page");
//             $i->amOnPage($indexPage->generateRoute('all'));
//             $position = 1;
//             $indexPage->seeHeadline($headline, $position);

//             $i->amOnPage($indexPage->generateRoute($topicSlug));
//             $indexPage->seeHeadline($headline, $position);
//         });
//         $i->amGoingTo("Make it unpublished");
//         $formPage
//             ->fillPublishDate(\Alaress_Date_Format::now()->addDays(2)->setTime(0, 0, 0))
//             ->submit();

//         $i->amGoingTo("Verify that user Alison Chan can no longer see it");
//         $alison->does(function (AcceptanceTester $i) use ($headline, $topicSlug) {
//             $indexPage = new Index($i);

//             $i->amGoingTo("Go to all page");
//             $i->amOnPage($indexPage->generateRoute('all'));
//             $position = 1;
//             $indexPage->dontSeeHeadline($headline, $position);

//             $i->amOnPage($indexPage->generateRoute($topicSlug));
//             $indexPage->dontSeeHeadline($headline, $position);
//         });
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @param Form             $formPage
//      * @param Index            $indexPage
//      * @param Item             $itemPage
//      *
//      * creates a news article via news/create
//      * check the draft is properly saved with the proper audience
//      * modify the audience a couple of times ensure it is properly updated
//      * publish the article and ensure the proper audience is presented
//      *
//      */
//     public function newsAudienceTest(AcceptanceTester $i, Form $formPage, Index $indexPage, Item $itemPage): void
//     {
//         $i->amGoingTo("Fill in form values with composite audience: 4 roles and 2 campuses + team and year level");
//         $timestamp = time();
//         $headline     = "Acceptance Test News Composite Audience Article Testing " . $timestamp;
//         $i->impersonateAnyUser('Jack Coles');
//         $i->amOnPage($formPage->generateCreateRoute());
//         $formPage
//             ->fillHeadline($headline)
//             ->createCompositeAudience('Team', 'Year Level', ['primary-IjEi', 'secondary-Ijki']);
//         $formPage->seeRoles(['1 Team & 1 Year Level']);
//         $formPage
//             ->createCompositeAudience('Role', 'Campus', ['primary-IjEi','primary-IjIi','primary-Ijci', 'primary-IjQi', 'secondary-IjEi','secondary-IjIi']);
//         $formPage->seeRoles(['2 Campuses & 4 Roles']);
//         $i->amGoingTo('check the draft article presents the proper audience: 4 roles and 2 campuses + 1 team and 1 year level');
//         $i->amOnPage('/news/me?status=Drafted');
//         $element = Locator::contains('div.small-12.card h3 a', 'Acceptance Test News Composite Audience Article Testing');
//         $i->click($element);
//         $formPage->seeRoles(['2 Campuses & 4 Roles','1 Team & 1 Year Level']);
//         $i->amGoingTo('check the modify article page presents the proper audience: 4 roles and 2 campuses + 1 team and 1 year level');
//         $itemPage->modifyArticle();
//         $formPage->seeRoles(['2 Campuses & 4 Roles','1 Team & 1 Year Level']);

//         $i->amGoingTo("Fill in form values with selecting audience: campus Primary + class 9A Eng (group members and parents)");
//         $formPage->removeAudience(['2 Campuses & 4 Roles','1 Team & 1 Year Level']);
//         $formPage->selectAudienceOption('Campuses', 'Primary', []);
//         $formPage->selectAudienceOption('Classes & Groups', '9A English (9ENGA)', ['audience-select-parents']);
//         $i->amGoingTo('check the modify article page presents the proper audience: Primary and 9A English (9ENGA) Inc parents of students');
//         $formPage->seeRoles(['Primary','9A English (9ENGA)','Class/Group','(Inc. parents of students)']);
//         $i->amGoingTo('check the draft article presents the proper audience: Primary and 9A English (9ENGA) Inc parents of students');
//         $i->amOnPage('/news/me?status=Drafted');
//         $element = Locator::contains('div.small-12.card h3 a', 'Acceptance Test News Composite Audience Article Testing');
//         $i->click($element);
//         $formPage->seeRoles(['9A English (9ENGA) (Inc. parents of students)','(Inc. parents of students)']);

//         $i->amGoingTo('Fill in form values by typing an audience: Jana Reed, Tim Reed and Jenni Reed');
//         // Grab user to generate fullname for removing audience
//         $janaReed = $i->grabUser('jreed');
//         $timReed = $i->grabUser('treed');
//         $jenniReed = $i->grabUser('jennireed01');

//         $itemPage->modifyArticle();
//         $formPage->removeAudience(['9A English (9ENGA)','Primary']);
//         $formPage->fillUsers([
//                                  $janaReed->getFirstname(),
//                                  $jenniReed->getFirstname(),
//                                  $timReed->getFirstname() . ' ' . $timReed->getLastname(), // tim is too short...
//                                  'Demo 1',
//                                  'Primary',
//                              ]);
//         $formPage->seeRoles([$janaReed->getFullName(), $jenniReed->getFullName(), $timReed->getFullName(),'Demo Class Page 1 (DEMO1)','Primary']);
//         $i->amGoingTo('check the draft article presents the proper audience: Primary and Demo class 1');
//         $i->amOnPage('/news/me?status=Drafted');
//         $element = Locator::contains('div.small-12.card h3 a', 'Acceptance Test News Composite Audience Article Testing');
//         $i->click($element);
//         $formPage->seeRoles(['Demo Class Page 1 (DEMO1)','Primary']);

//         $i->amGoingTo('Fill in form values by selecting audience via bricks and composite: year level + house and Teams Raiders');
//         $itemPage->modifyArticle();

//         $formPage->removeAudience([
//             $this->getAudienceText($janaReed),
//             $this->getAudienceText($timReed),
//             $this->getAudienceText($jenniReed),
//             'Primary',
//             'Demo Class Page 1 (DEMO1)']);

//         $formPage->selectAudienceOption('Year Levels', 'Year 9', ['audience-select-parents']);
//         $formPage->createCompositeAudience('House', 'Team', ['primary-IjEi','secondary-IjIi']);
//         $formPage->seeRoles(['1 Team & 1 House','Year 9','Composite Audience (view details)','Year Level','Inc. parents of students']);
//         $i->amGoingTo('check the draft article presents the proper audience: Primary and Demo class 1');
//         $i->amOnPage('/news/me?status=Drafted');
//         $element = Locator::contains('div.small-12.card h3 a', 'Acceptance Test News Composite Audience Article Testing');
//         $i->click($element);
//         $formPage->seeRoles(['1 Team & 1 House','Year 9','Inc. parents of students']);
//         $i->amGoingTo('Publish the news article and ensure the audience remains the same');
//         $itemPage->modifyArticle();
//         $formPage->submit();
//         $i->waitForText('Your article has been submitted for moderation', 10);
//         $formPage->seeRoles(['1 Team & 1 House','Year 9','Inc. parents of students']);
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @param Form             $formPage
//      * @param Index            $indexPage
//      * @param Item             $itemPage
//      * @throws Exception
//      *
//      * creates a news article via news component in a class page
//      * check the draft is properly saved with the proper audience
//      * modify the audience a couple of times ensure it is properly updated
//      * publish the article and ensure the proper audience is presented
//      *
//      */
//     public function newsAudienceViaClassPageTest(AcceptanceTester $i, Form $formPage, Index $indexPage, Item $itemPage): void
//     {
//         $this->createFolder($i);
//         $i->amGoingTo("Navigate to the class page");
//         $i->click('News Folder Test');
//         $i->amGoingTo("Add a News component into the page");
//         $homepage = new Homepage($i);
//         $homepage->addComponent(Homepage::ELEMENT_ADD_COMPONENT_SECTION_INFORMATION, 'News');
//         // Enter content into the new component
//         $layout   = Homepage::ELEMENT_COMPONENT_LAYOUT_TOP;
//         $position = 1;
//         $homepage->clickComponentEmptyStateLink($layout, $position);
//         $timestamp = time();
//         $headline     = "Acceptance Test News Audience Article via Class Page Testing " . $timestamp;
//         $formPage
//             ->fillHeadline($headline)
//             ->seeRoles(['News Folder Test']);
//         $i->waitForElementClickable($formPage::FORM_SAVE);
//         $i->amGoingTo("check the draft article presents the proper audience: News Folders Test");
//         $i->amOnPage('/news/me?status=Drafted');
//         $element = Locator::contains('div.small-12.card h3 a', 'Acceptance Test News Audience Article via Class Page Testing');
//         $i->click($element);
//         $i->canSee('News Folder Test');
//         $i->amGoingTo("check the modify article page presents the proper audience: News Folders Test");
//         $itemPage->modifyArticle();
//         $formPage->seeRoles(['News Folder Test']);
//         $i->amGoingTo("Add new audience to the news article: Eden Reed and Teacher role");
//         $formPage->selectAudienceOption('Roles', 'Teacher', []);
//         $formPage->fillUsers(['Eden Reed']);
//         $i->amGoingTo("Check the audience is properly presented: News Folders, Eden Reed and Teacher");
//         $formPage->seeRoles(['News Folder Test','Teacher','Ms Eden Reed (506) (Junior Student)']);
//         $i->wait(4);
//         $i->amGoingTo('check the draft article presents the proper audience: News Folders and Teacher');
//         $i->amOnPage('/news/me?status=Drafted');
//         $element = Locator::contains('div.small-12.card h3 a', 'Acceptance Test News Audience Article via Class Page Testing');
//         $i->click($element);
//         $formPage->seeRoles(['News Folder Test','Teacher']);
//         $i->amGoingTo("check the modify article page presents the proper audience: News Folders, Eden Reed and Teacher");
//         $itemPage->modifyArticle();
//         $i->wait(4);
//         $formPage->seeRoles(['News Folder Test','Teacher','Ms Eden Reed (506) (Junior Student)']);
//         $formPage->submit();
//         $i->waitForText('PUBLISHED', 10);
//         $formPage->seeRoles(['News Folder Test','Teacher']);
//     }

//     /**
//      * Convenience helper to create a folder
//      * @param AcceptanceTester $i
//      * @throws Exception
//      */
//     private function createFolder(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("Create a folder page to create articles for that audience");
//         $createFolder = new createFolder($i);
//         $manageGroup = new ManageGroup($i);

//         $folderName = "News Folder Test";
//         $homepageTemplateOption = "Class";

//         $i->amOnPage($createFolder::URL);
//         $createFolder->waitCreateFolderTitleToBePresented();
//         $createFolder->typeName($folderName);
//         $createFolder->setHomepageTemplate($homepageTemplateOption);
//         $createFolder->setAccessPermission('groupWrite1');
//         $createFolder->setAccessPermission('groupWrite2');
//         $i->amGoingTo("give senior student write permission");
//         $createFolder->setAccessPermission('groupWrite3');
//         $createFolder->setAccessPermission('groupWrite4');
//         $i->amGoingTo("give junior student write permission");
//         $createFolder->setAccessPermission('groupRead5');
//         $i->amGoingTo("give middle student write permission");
//         $createFolder->setAccessPermission('groupNone6');
//         $createFolder->clickCreateGroupToggle();
//         $createFolder->clickCreateFolderButton();
//         $manageGroup->waitManageGroupTitleToBePresented();
//         $manageGroup->setAccessPermission('type-3');
//         $manageGroup->clickUpdateButton();
//     }

//     /**
//      * @param User $user
//      * @return string
//      */
//     private function getAudienceText(User $user): string
//     {
//         $externalId = $user->getExternalId() ? ' (' . $user->getExternalId() . ')' : '';
//         return $user->getFullName() . $externalId . ' (' . $user->getRole()->getName() . ')';
//     }

//     /**
//      * Soft deleting data to not impact the tests
//      *
//      * @param AcceptanceTester $i
//      */
//     public function tearDown(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
//         $i->updateInDatabase('folder', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => "News Folder Test"]);
//         $i->updateInDatabase('comms_news_article', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => "Acceptance Test News Article Testing%"]);
//         $i->updateInDatabase('comms_news_article', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => "Acceptance Test News Composite Audience Article Testing%"]);
//         $i->updateInDatabase('comms_news_article', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => "Acceptance Test News Audience Article via Class Page Testing%"]);
//     }
// }
// import { test, expect } from "@schoolboxTest/playwright/support/integrations";
// import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";
// import PastoralProfile from "@schoolboxTest/playwright/support/page/pastoralCare/PastoralProfile";
// import CreatePastoralRecord from "@schoolboxTest/playwright/support/page/pastoralCare/CreatePastoralRecord";
// import Common from "@schoolboxTest/playwright/support/page/Common";

// /**
//  * creates a pastoral record, check the pastoral record details, comments it,
//  * deletes the comment, modify the record, delete the record
//  *
//  * Steps:
//  * 1. Provision sales data
//  * 2. Execute npx playwright test PastoralRecords.spec.ts
//  *
//  */
// test.describe("Pastoral Records", () => {
//     /**
//      * Preparations
//      *
//      * get logged in as admin
//      */
//     test.beforeEach(async ({ tester }) => {
//         await tester.loginAsAdmin();
//     });

//     /**
//      * Deleting data when the test is about to start so it does not get impacted by other tests already executed
//      * @param baseTester
//      * @param db
//      */
//     test.beforeAll(async ({ baseTester, db }) => {
//         baseTester.isGoingTo("update delete_at for the pastoral records so it does not impact the tests");
//         await db.updateInDatabase(
//             "pastoral",
//             { deleted_at: "2022-01-15 11:22:34" },
//             { heading: "Automation Test Pastoral Record%" },
//         );
//         await db.updateInDatabase(
//             "pastoral",
//             { deleted_at: "2022-01-15 11:22:34" },
//             { heading: "Automation Test View Pastoral Record%" },
//         );
//         await db.updateInDatabase(
//             "pastoral",
//             { deleted_at: "2022-01-15 11:22:34" },
//             { heading: "This is an automation pastoral record updated%" },
//         );
//         await db.updateInDatabase("config", { value: 1 }, { name: "pastoral_student_access" });
//     });

//     /**
//      *
//      * navigate to the student pastoral care page
//      * create a pastoral record for the student
//      * ensure the record is created with the proper details
//      */
//     test("Create Pastoral Record", async ({ tester, page }) => {
//         const profilePage = new ProfilePage(tester, page);
//         const pastoralProfile = new PastoralProfile(tester, page);
//         const createPastoralRecord = new CreatePastoralRecord(tester, page);
//         const common = new Common(page);
//         const studentId = "25";
//         const pastoralTitle = "Automation Test Pastoral Record";
//         const pastoralDescription = "This is an automation pastoral record";
//         tester.isGoingTo("navigate to the student profile page");
//         await profilePage.goto(studentId);
//         tester.isGoingTo("click the pastoral care button");
//         await profilePage.clickButton("pastoral");
//         tester.isGoingTo("click create pastoral record and create a pastoral record");
//         await pastoralProfile.clickCreatePastoralRecord();
//         await createPastoralRecord.typeTitle(pastoralTitle);
//         await createPastoralRecord.clickTypeDropdown();
//         await createPastoralRecord.selectTypeSubType("1");
//         const body = page.locator(`#body_public`);
//         await tester.fillCKEditorByLocator(body, pastoralDescription);
//         await createPastoralRecord.clickVisibilityLink("Role");
//         await createPastoralRecord.clickPastoralRole("Administration");
//         await common.closeModal();
//         await createPastoralRecord.clickSave();
//         tester.isGoingTo("ensure the record is presented under pastoral records");
//         await pastoralProfile.goto(studentId);
//         await expect(page.locator("#pastoralRecords")).toContainText(pastoralTitle);
//     });

//     /**
//      * navigate to the student pastoral care page
//      * comment a pastoral record for the student
//      * ensure the comment is created with the proper details
//      * delete the comment
//      * ensure the comment is deleted
//      */

//     test("Comment Pastoral Record", async ({ tester, page, db }) => {
//         const pastoralProfile = new PastoralProfile(tester, page);
//         const common = new Common(page);
//         const studentId = "25";
//         const pastoralComment = "This is an automation pastoral comment";
//         tester.isGoingTo("navigate to the student pastoral profile page");
//         await pastoralProfile.goto(studentId);
//         tester.isGoingTo("click the pastoral record");
//         const recordId = (await db.grabFromDatabase("pastoral", "id", {
//             deleted_at: null,
//             "heading like": "Automation Test Pastoral Record%",
//         })) as number;
//         await pastoralProfile.clickPastoralRecord(studentId, recordId);
//         await common.clickAddComment();
//         await tester.fillCKEditorById("comment-public", pastoralComment);
//         await pastoralProfile.clickPostComment();
//         tester.isGoingTo("check the comment is presented");
//         await expect(page.locator("#pastoral-record-comments-container-public")).toContainText(pastoralComment);
//         tester.isGoingTo("delete the pastoral comment");
//         await pastoralProfile.clickDeleteCommentIcon();
//         await common.clickModalDeleteButton();
//         await expect(page.locator("#pastoral-record-comments-container-public")).not.toContainText(pastoralComment);
//     });

//     /**
//      * navigate to the student pastoral care page
//      * modify a pastoral record for the student
//      * ensure the record is updated with the proper details
//      */

//     test("Modify Pastoral Record", async ({ tester, page, db }) => {
//         const pastoralProfile = new PastoralProfile(tester, page);
//         const common = new Common(page);
//         const createPastoralRecord = new CreatePastoralRecord(tester, page);
//         const studentId = "25";
//         const pastoralTitle = "This is an automation pastoral record updated";
//         tester.isGoingTo("navigate to the pastoral record and click the modify button");
//         await pastoralProfile.goto(studentId);
//         const recordId = (await db.grabFromDatabase("pastoral", "id", {
//             deleted_at: null,
//             "heading like": "Automation Test Pastoral Record%",
//         })) as number;
//         await pastoralProfile.clickPastoralRecord(studentId, recordId);
//         await common.clickIconModifyPage();
//         tester.isGoingTo("get the pastoral record updated");
//         await createPastoralRecord.typeTitle(pastoralTitle);
//         await createPastoralRecord.clickTypeDropdown();
//         await createPastoralRecord.selectTypeSubType("2");
//         await createPastoralRecord.clickSave();
//         tester.isGoingTo("ensure the record are properly updated");
//         await pastoralProfile.goto(studentId);
//         await expect(page.locator("#pastoralRecords")).toContainText(pastoralTitle);
//         await pastoralProfile.clickPastoralRecord(studentId, recordId);
//         await expect(page.locator("#content")).toContainText("Attendance");
//     });

//     /**
//      * navigate to the student pastoral care page
//      * delete a pastoral record for the student
//      * ensure the record is no longer presented
//      */
//     test("Delete Pastoral Record", async ({ tester, page, db }) => {
//         const pastoralProfile = new PastoralProfile(tester, page);
//         const common = new Common(page);
//         const studentId = "25";
//         tester.isGoingTo("navigate to the pastoral profile page and delete a pastoral record");
//         await pastoralProfile.goto(studentId);
//         const pastoralTitle = "This is an automation pastoral record updated";
//         const recordId = (await db.grabFromDatabase("pastoral", "id", {
//             deleted_at: null,
//             "heading like": pastoralTitle,
//         })) as number;
//         await pastoralProfile.clickDeleteIcon(recordId);
//         await common.clickConfirmButton();
//         tester.isGoingTo("ensure the record is no longer presented");
//         await page.waitForTimeout(2000);
//         await expect(page.locator("#pastoralRecords")).not.toContainText(pastoralTitle);
//     });

//     /**
//      * navigate to the student pastoral care page
//      * create a pastoral record for the student
//      * ensure the record details is properly presented in the pastoral record page
//      */

//     test("View Pastoral Record", async ({ tester, page, db }) => {
//         const profilePage = new ProfilePage(tester, page);
//         const pastoralProfile = new PastoralProfile(tester, page);
//         const createPastoralRecord = new CreatePastoralRecord(tester, page);
//         const common = new Common(page);
//         const studentId = "25";
//         const pastoralTitle = "Automation Test View Pastoral Record";
//         const pastoralDescription = "This is an automation pastoral record";
//         tester.isGoingTo("navigate to the student profile page");
//         await profilePage.goto(studentId);
//         tester.isGoingTo("click the pastoral care button");
//         await profilePage.clickButton("pastoral");
//         tester.isGoingTo("click create pastoral record and create a pastoral record  visible to parents and students");
//         await pastoralProfile.clickCreatePastoralRecord();
//         await createPastoralRecord.typeTitle(pastoralTitle);
//         await createPastoralRecord.clickTypeDropdown();
//         await createPastoralRecord.selectTypeSubType("1");
//         const body = page.locator(`#body_public`);
//         await tester.fillCKEditorByLocator(body, pastoralDescription);
//         await createPastoralRecord.clickVisibilityLink("Role");
//         await createPastoralRecord.clickPastoralRole("Student");
//         await createPastoralRecord.clickPastoralRole("Parent");
//         await common.closeModal();
//         await createPastoralRecord.clickSave();
//         tester.isGoingTo("ensure the record is presented under pastoral records");
//         await pastoralProfile.goto(studentId);
//         await expect(page.locator("#pastoralRecords")).toContainText(pastoralTitle);
//         tester.isGoingTo("as a parent check the pastoral care records details");
//         await tester.impersonateViaModifyUserPage("Ms Jenni Reed");
//         await pastoralProfile.goto(studentId);
//         const recordId = (await db.grabFromDatabase("pastoral", "id", {
//             deleted_at: null,
//             "heading like": "Automation Test View Pastoral Record%",
//         })) as number;
//         await pastoralProfile.clickPastoralRecord(studentId, recordId);
//         await expect(page.locator("#content")).toContainText("Automation Test View Pastoral Record");
//         await expect(page.locator("#content")).toContainText("Academic");
//         await expect(page.locator("#content")).toContainText("Award/Achievement");
//         tester.isGoingTo("as a student check the pastoral care records details");
//         await tester.impersonateViaModifyUserPage("Ms Jana Reed");
//         await pastoralProfile.goto(studentId);
//         await pastoralProfile.clickPastoralRecord(studentId, recordId);
//         await expect(page.locator("#content")).toContainText("Automation Test View Pastoral Record");
//         await expect(page.locator("#content")).toContainText("Academic");
//         await expect(page.locator("#content")).toContainText("Award/Achievement");
//     });

//     /**
//      * Soft deleting data to not impact the tests
//      */

//     test.afterAll(async ({ baseTester, db }) => {
//         baseTester.isGoingTo(
//             "update delete_at for the data created in this test so it does impact next suite of tests",
//         );
//         await db.updateInDatabase(
//             "pastoral",
//             { deleted_at: "2022-01-15 11:22:34" },
//             { heading: "Automation Test Pastoral Record%" },
//         );
//         await db.updateInDatabase(
//             "pastoral",
//             { deleted_at: "2022-01-15 11:22:34" },
//             { heading: "Automation Test View Pastoral Record%" },
//         );
//         await db.updateInDatabase(
//             "pastoral",
//             { deleted_at: "2022-01-15 11:22:34" },
//             { heading: "This is an automation pastoral record updated%" },
//         );
//         await db.updateInDatabase("config", { value: 0 }, { name: "pastoral_student_access" });
//     });
// });
