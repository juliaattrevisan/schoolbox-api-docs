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

    test("Create news article adding audiende via bricks", async ({ tester, page, db }) => {
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
        await form.clickAddAudience();
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
        await form.clickAddAudience();
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

    test("News Audience Test composite audience", async ({ tester, page, db }) => {
        const form = new Form(tester, page);
        const index = new Index(tester, page);
        const item = new Item(tester, page);
        tester.isGoingTo("Fill in form values with composite audience: 4 roles and 2 campuses + team and year level");
        const headline = "Acceptance Test News Composite Audience Article Testing ";
        await tester.impersonateViaModifyUserPage("Mr Jack Coles");
        await form.goto();
        await form.fillHeadline(headline);
        await form.createCompositeAudience("Team", ["primary-IjEi"], "Year Level", ["secondary-Ijki"]);
        await form.seeAudience("1 Team & 1 Year Level");

        await form.createCompositeAudience("Campus", ["primary-IjEi", "primary-IjIi"], "Role", [
            "secondary-IjEi",
            "secondary-IjIi",
            "secondary-IjQi",
            "secondary-Ijci",
        ]);
        await form.seeAudience("2 Campuses & 4 Roles");

        tester.isGoingTo(
            "check the draft article presents the proper audience: 4 roles and 2 campuses + 1 team and 1 year level",
        );
        await page.goto("/news/me?status=Drafted");
        const articleId = (await db.grabFromDatabase("comms_news_article", "id", {
            title: headline,
            deleted_at: null,
        })) as number;
        await index.clickArticleTitle(articleId);
        await form.seeAudience("2 Campuses & 4 Roles");
        await form.seeAudience("1 Team & 1 Year Level");

        tester.isGoingTo(
            "check the modify article page presents the proper audience: 4 roles and 2 campuses + 1 team and 1 year level",
        );
        await item.modifyArticle();
        await form.seeAudience("2 Campuses & 4 Roles");
        await form.seeAudience("1 Team & 1 Year Level");

        tester.isGoingTo(
            "Fill in form values with selecting audience: campus Primary + class 9A Eng (group members and parents)",
        );
        await form.removeAudience("2 Campuses & 4 Roles");
        await form.removeAudience("1 Team & 1 Year Level");
        await form.selectAudienceOption("Campuses");
        await form.checkboxAudience("Primary");
        await form.clickAddAudience();
        await form.selectAudienceOption("Groups");
        await form.checkboxAudience("9A English (9ENGA)");
        await form.selectIncludeUsers("parents");
        await form.clickAddAudience();
        tester.isGoingTo(
            "check the modify article page presents the proper audience: Primary and 9A English (9ENGA) Inc parents of students",
        );
        await form.seeAudience("Primary");
        await form.seeAudience("9A English (9ENGA)");
        await form.seeAudience("Class/Group");
        await form.seeAudience("(Inc. parents of students)");

        tester.isGoingTo(
            "check the draft article presents the proper audience: Primary and 9A English (9ENGA) Inc parents of students",
        );
        await page.goto("/news/me?status=Drafted");
        await index.clickArticleTitle(articleId);
        await form.seeAudience("9A English (9ENGA) (Inc. parents of students)");
        await form.seeAudience("(Inc. parents of students)");

        tester.isGoingTo("'Fill in form values by typing an audience: Jana Reed, Tim Reed and Jenni Reed");
        await item.modifyArticle();

        await form.removeAudience("9A English (9ENGA)");
        await form.removeAudience("Primary");
        await form.fillUser("Jana Reed");
        await form.fillUser("Tim Reed");
        await form.fillUser("Jenni Reed");
        await form.fillUser("Demo 1");
        await form.fillUser("Primary");
        // await the draft to be saved - cuurrently it takes 3 seconds
        await page.waitForTimeout(3000);

        tester.isGoingTo("check the draft article presents the proper audience: Primary and Demo class 1");
        await page.goto("/news/me?status=Drafted");
        await index.clickArticleTitle(articleId);
        await item.seeArticleAudience("Primary");
        await item.seeArticleAudience("Demo Class Page 1 (DEMO1)");

        tester.isGoingTo(
            "Fill in form values by selecting audience via bricks and composite: year level + house and Teams Raide",
        );
        await item.modifyArticle();

        await form.removeAudience("Ms Jana Reed (507) (Middle Student)");
        await form.removeAudience("Ms Tim Reed (509) (Senior Student)");
        await form.removeAudience("Ms Jenni Reed (502) (Senior Student)");
        await form.removeAudience("Primary");
        await form.removeAudience("Demo Class Page 1 (DEMO1)");

        await form.selectAudienceOption("Year Levels");
        await form.checkboxAudience("Year 9");
        await form.selectIncludeUsers("parents");
        await form.createCompositeAudience("House", ["primary-IjEid"], "Team", ["secondary-IjIi"]);
        await form.seeAudience("1 Year Level & 1 House");
        await form.seeAudience("Year 9");
        await form.seeAudience("Composite Audience (view details)");
        await form.seeAudience("Year Level");
        await form.seeAudience("Inc. parents of students");

        tester.isGoingTo("check the draft article presents the proper audience: Year 9 and 1 House and 1 Team");
        await page.goto("/news/me?status=Drafted");
        await index.clickArticleTitle(articleId);
        await item.seeArticleAudience("1 Team & 1 House");
        await item.seeArticleAudience("Year 9");
        await item.seeArticleAudience("Inc. parents of students");

        tester.isGoingTo("Publish the article and ensure the proper audience is presented");
        await item.modifyArticle();
        await form.submit();
        await expect(page.locator('div[id="flash"]')).toContainText(
            "Your article has been submitted for moderation. You will receive a notification when your article is rejected or approved.",
        );
        await item.seeArticleAudience("1 Team & 1 House");
        await item.seeArticleAudience("Year 9");
        await item.seeArticleAudience("Inc. parents of students");
    });

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
        await form.clickAddAudience();
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
