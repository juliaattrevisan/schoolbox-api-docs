import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Form from "@schoolboxTest/playwright/support/page/news/Form";
import Index from "@schoolboxTest/playwright/support/page/news/Index";
import Item from "@schoolboxTest/playwright/support/page/news/Item";

/**
 * create a news article, unpublish it, publish it, audience view the article, enable comments,
 * add comments, delete comments, disable comments, delete the news article
 *
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. npx playwright test newsInitialCest
 */

test.describe("Create a News Article", () => {
    /**
     * Preparations
     *
     * get logged in as admin and navigiating to create news page
     */
    test.beforeEach(async ({ tester }) => {
        await tester.logout();
        await tester.loginAsAdmin();
    });

    test("Create a News Article and publish it", async ({ tester, page }) => {
        const form = new Form(tester, page);
        const index = new Index(tester, page);

        const headline = "Test News Title";
        const article = "wow what interesting news";
        const audienceOption = "Year Levels";
        const yearLevel = "Year 9";

        tester.isGoingTo("Go to the create news article page");
        await form.goto();
        await form.fillHeadline(headline);
        await form.fillArticle(article);
        await form.selectAudienceOption(audienceOption);
        await form.checkboxAudience(yearLevel);
        await form.clickAddAudience();
        await form.submit();
        // had to add this as it was going away from this page too quickly and not publishing
        await page.waitForURL("/");

        tester.isGoingTo("Verify that the news is in the All topic, and is the first news item");
        await index.goto("all");
        await index.seeHeadline(headline);
    });

    test("Unpublish the News Article", async ({ tester, page, db }) => {
        const index = new Index(tester, page);
        const item = new Item(tester, page);

        const headline = "Test News Title";
        const articleId = (await db.grabFromDatabase("comms_news_article", "id", {
            title: headline,
            deleted_at: null,
        })) as number;

        tester.isGoingTo("Unpublish the news article");
        await index.goto("all");
        await index.viewArticle(articleId);
        // added this timeout as it was clicking the 3-dot button too early
        await page.waitForTimeout(1000);
        await item.unpublishArticle();

        tester.isGoingTo("Verify that the news article is unpublished");
        await index.goto("all");
        await index.dontSeeHeadline(headline);
        await page.goto(`/news/me?status=Rejected`);
        await index.seeHeadline(headline);
    });

    test("Publish the News Article Again", async ({ tester, page, db }) => {
        const index = new Index(tester, page);
        const item = new Item(tester, page);

        const headline = "Test News Title";
        const articleId = (await db.grabFromDatabase("comms_news_article", "id", {
            title: headline,
            deleted_at: null,
        })) as number;

        tester.isGoingTo("Publish the news article again");
        await page.goto(`/news/me?status=Rejected`);
        await index.clickArticleTitle(articleId);
        await item.clickPublishArticle();
        await page.waitForTimeout(1000);

        tester.isGoingTo("Verify that the news article is published");
        await index.goto("all");
        await index.seeHeadline(headline);
    });

    test("View the News Article as Audience", async ({ tester, page }) => {
        const index = new Index(tester, page);

        const headline = "Test News Title";

        tester.isGoingTo("Impersonate a student and view the news article");
        await tester.impersonate("Jana Reed");
        await index.goto("all");
        await index.seeHeadline(headline);
    });

    test("Enable News Comments", async ({ tester, page, db }) => {
        const index = new Index(tester, page);
        const item = new Item(tester, page);

        const headline = "Test News Title";
        const articleId = (await db.grabFromDatabase("comms_news_article", "id", {
            title: headline,
            deleted_at: null,
        })) as number;

        tester.isGoingTo("Enable news comments");
        await index.goto("all");
        await index.viewArticle(articleId);
        await item.enableComment();
        await expect(page.locator(`button[class="show-ckeditor-button"]`)).toBeVisible();
        await expect(page.locator(`button[class="show-ckeditor-button"]`)).toContainText("Add a comment");
    });

    test("Add News Comments", async ({ tester, page, db }) => {
        const index = new Index(tester, page);
        const item = new Item(tester, page);

        const headline = "Test News Title";
        const articleId = (await db.grabFromDatabase("comms_news_article", "id", {
            title: headline,
            deleted_at: null,
        })) as number;

        tester.isGoingTo("Add news comments");
        await index.goto("all");
        await index.viewArticle(articleId);
        await item.clickAddComment();
        await item.typeComment("indeed this is interesting news");
        await item.postComment();
        await item.typeComment("this is a second comment");
        await item.postComment();
        await expect(page.locator(`ul.information-list`).first()).toContainText("indeed this is interesting news");
        await expect(page.locator(`ul.information-list`).first()).toContainText("this is a second comment");
    });

    test("Delete News Comments", async ({ tester, page, db }) => {
        const index = new Index(tester, page);
        const item = new Item(tester, page);

        const headline = "Test News Title";
        const articleId = (await db.grabFromDatabase("comms_news_article", "id", {
            title: headline,
            deleted_at: null,
        })) as number;

        tester.isGoingTo("Delete news comments");
        await index.goto("all");
        await index.viewArticle(articleId);
        await item.deleteComment();
        await page.reload();
        await item.deleteComment();
        await expect(page.locator(`ul.information-list`).first()).not.toContainText("indeed this is interesting news");
        await expect(page.locator(`ul.information-list`).first()).not.toContainText("this is a second comment");
    });

    test("Disable News Comments", async ({ tester, page, db }) => {
        const index = new Index(tester, page);
        const item = new Item(tester, page);

        const headline = "Test News Title";
        const articleId = (await db.grabFromDatabase("comms_news_article", "id", {
            title: headline,
            deleted_at: null,
        })) as number;

        tester.isGoingTo("Disable news comments");
        await index.goto("all");
        await index.viewArticle(articleId);
        await item.enableComment();
        await expect(page.locator(`button[class="show-ckeditor-button"]`)).not.toBeVisible();
        await expect(page.locator(`span[class="icon-locked"]`)).toContainText(
            "Comments are currently disabled for this article.",
        );
    });

    test("Delete the News Article", async ({ tester, page, db }) => {
        const index = new Index(tester, page);
        const item = new Item(tester, page);

        const headline = "Test News Title";
        const articleId = (await db.grabFromDatabase("comms_news_article", "id", {
            title: headline,
            deleted_at: null,
        })) as number;

        tester.isGoingTo("Delete the news article");
        await index.goto("all");
        await index.viewArticle(articleId);
        await item.deleteArticle(articleId);
        await index.goto("all");
        await index.dontSeeHeadline(headline);
    });

    /**
     * Clean up
     */
    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("update delete_at for the news article so it does not impact the news tests");
        await db.updateInDatabase(
            "comms_news_article",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Acceptance Test News%", deleted_at: null },
        );
    });
});
