import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { Page, expect } from "@playwright/test";

/**
 * News item page object - this is related to when viewing a single news article
 */

export default class Item {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/news/item/{id}";

    // Selectors

    static readonly DATA_TEST_DOT_MENU = "sbx-pop-out-header-options";

    static readonly DATA_TEST_DOT_MENU_BUTTON = "sbx-pop-out-header-options-button";

    static readonly LINK_MODIFY = "a.icon-modify";

    static readonly LINK_PREVIOUS = ".icon-previous";

    static readonly LINK_NEXT = ".icon-next";

    static readonly ELEMENT_BREADCRUMB = ".breadcrumb";

    static readonly ELEMENT_HEADLINE = "h1";

    static readonly ELEMENT_META_AUTHOR = ".card > .meta:nth-of-type(1)";

    static readonly ELEMENT_META_DATE = ".card > .meta:nth-of-type(2)";

    static readonly ELEMENT_FEATURE_IMAGE = ".large-3 img";

    static readonly ELEMENT_ARTICLE = "article";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Generates route for this page.
     * @param newsId - The topic
     */
    async goto(newsId: string): Promise<void> {
        await this.page.goto(Item.generateRoute(newsId));
    }

    /**
     * Generates route for this page
     * @param newsId - The ID of the page
     * @returns - the URL for the page
     */
    public static generateRoute(newsId: string): string {
        return Item.URL.replace("{id}", newsId);
    }

    /**
     * Do I see this headline?
     * @param headline - The headline to check
     */
    async seeHeadline(headline: string): Promise<void> {
        await expect(this.page.locator(Item.ELEMENT_HEADLINE)).toHaveText(headline);
    }

    /**
     * Do I see this article's body?
     * @param article - The article to check
     */
    async seeArticle(article: string): Promise<void> {
        await expect(this.page.locator(Item.ELEMENT_ARTICLE)).toHaveText(article);
    }

    /**
     * Follow this article's modify link.
     */
    async modifyArticle(): Promise<void> {
        await expect(this.page.getByTestId(Item.DATA_TEST_DOT_MENU)).toBeEnabled();
        await this.page.getByTestId(Item.DATA_TEST_DOT_MENU_BUTTON).click();
        await this.page.locator(Item.LINK_MODIFY).last().click();
        await expect(this.page.locator('h1:has-text("Update news")')).toBeVisible();
    }
}
