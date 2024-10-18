import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { Page, expect } from "@playwright/test";

/**
 * News index page object - this is related to the news page where all the news articles are listed
 */
export default class Index {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/news?topic={topic}";

    // Selectors

    static readonly LINK_ADD_NEWS = ".icon-add";

    static readonly LINK_ARCHIVE = ".icon-archive";

    static readonly LINK_RSS = ".icon-rss";

    static readonly ELEMENT_BREADCRUMB = ".breadcrumb";

    static readonly ELEMENT_FILTER_TOPIC = ".tabs";

    static readonly ELEMENT_NEWS_LIST = ".information-list";

    static readonly ELEMENT_NEWS_LIST_ITEM = ".actions-small-1";

    static readonly ELEMENT_NEWS_LIST_ITEM_HEADLINE = "div.small-12.card h3 a";

    static readonly ELEMENT_NEWS_LIST_ITEM_ARTICLE = ".card article";

    static readonly ELEMENT_NEWS_LIST_ITEM_META_AUTHOR = ".card > .meta:nth-of-type(1)";

    static readonly ELEMENT_NEWS_LIST_ITEM_META_DATE = ".card > .meta:nth-of-type(2)";

    static readonly LINK_NEWS_ITEM_VIEW = ".card > a";

    static readonly LINK_NEWS_ITEM_MODIFY = "a.icon-modify";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Generates route for this page.
     * @param newsTopic - The topic
     */
    async goto(newsTopic: string): Promise<void> {
        await this.page.goto(Index.generateRoute(newsTopic));
    }

    /**
     * Generates route for this page
     * @param newsTopic - The ID of the page
     * @returns - the URL for the page
     */
    public static generateRoute(newsTopic: string): string {
        return Index.URL.replace("{topic}", newsTopic);
    }

    /**
     * Do I see thiis headline on this page?
     * @param headline - news article headline
     */
    async seeHeadline(headline: string): Promise<void> {
        await expect(this.page.locator(`a:has-text("${headline}")`)).toBeVisible();
    }

    /**
     * Do I NOT see this headline on this page?
     * @param headline - news article headline
     */
    async dontSeeHeadline(headline: string): Promise<void> {
        await expect(this.page.locator(`a:has-text("${headline}")`)).not.toBeVisible();
    }

    /**
     * Do I see this article summary on this page?
     * @param article - news article summary
     */
    async seeArticleSummary(article: string): Promise<void> {
        await expect(this.page.locator(`article:has-text("${article}")`)).toBeVisible();
    }

    /**
     * Do I NOT see this article summary on this page?
     * @param article - news article summary
     */
    async dontSeeArticleSummary(article: string): Promise<void> {
        await expect(this.page.locator(`article:has-text("${article}")`)).not.toBeVisible();
    }

    /**
     * View an article.
     * @param articleId - Article number to view
     */
    async viewArticle(articleId: number): Promise<void> {
        await this.page.locator(`a[href="/news/${articleId}?ref=index"]`).last().click();
    }

    /**
     * Modify an article.
     * @param articleId - Article number to view
     */
    async modifyArticle(articleId: number): Promise<void> {
        await this.page.locator(`a[href="/news/${articleId}/update"]`).click();
    }
}
