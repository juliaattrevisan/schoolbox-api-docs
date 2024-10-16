import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { Page, expect } from "@playwright/test";
import Common from "@schoolboxTest/playwright/support/page/Common";

// this page object is for the create news page
export default class Form {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/news/create";

    static readonly ARTICLE_TITLE_CKEDITOR = 'input[name="title"]';

    static readonly ARTICLE_BODY_CKEDITOR = 'textarea[name="body"]';

    static readonly LINK_ADD_NEWS = ".icon-add";

    static readonly FORM = "#newsForm";

    static readonly FORM_SAVE = "#news-submit";

    static readonly ARTICLE_HEADLINE = "title";

    static readonly DATA_TEST_ARTICLE_BODY = "news-article-form-body";

    static readonly DATA_TEST_ARTICLE_BLURB = "news-article-form-blurb";

    private filePath?: string;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the create route
     */
    async goto(): Promise<void> {
        await this.page.goto(Form.URL);
    }

    /**
     * submit the form
     */
    async submit(): Promise<void> {
        await expect(this.page.locator(Form.FORM_SAVE)).toBeEnabled({ timeout: 30000 });
        await this.page.locator(Form.FORM_SAVE).click();
    }

    /**
     * fill headline
     * @param headline - The headline to fill
     */
    async fillHeadline(headline: string): Promise<void> {
        await this.page.fill(Form.ARTICLE_TITLE_CKEDITOR, headline);
    }

    /**
     * see headline
     * @param headline - The headline to see
     */
    async seeHeadline(headline: string): Promise<void> {
        await expect(this.page.locator(Form.ARTICLE_HEADLINE)).toHaveText(headline);
    }

    /**
     * fill article
     * @param article - The article to fill
     */

    async fillArticle(articleBody: string): Promise<void> {
        const body = this.page.getByTestId(Form.DATA_TEST_ARTICLE_BODY);
        await this.tester.fillCKEditorByLocator(body, articleBody);
    }

    /**
     * see article
     * @param headline - The article to see
     */
    async seeArticle(headline: string): Promise<void> {
        await expect(this.page.locator(Form.DATA_TEST_ARTICLE_BODY)).toHaveText(headline);
    }

    /**
     * fill blurb
     * @param blurbBody - The blurb to fill
     */
    async fillBlurb(blurbBody: string): Promise<void> {
        await this.page.locator("legend.icon-down-arrow").click();
        const blurb = this.page.getByTestId(Form.DATA_TEST_ARTICLE_BLURB);
        await this.tester.fillCKEditorByLocator(blurb, blurbBody);
    }

    /**
     * see blurb
     * @param blurb - The blurb to see
     */
    async seeBlurb(blurb: string): Promise<void> {
        await this.page.locator("legend.icon-down-arrow").click();
        await expect(this.page.locator("blurb")).toHaveText(blurb);
    }

    /**
     * fill feature image
     * @param filePath - The filename to fill
     */
    async attachFeatureImage(filePath: string): Promise<void> {
        // await this.page.setInputFiles("#featureImage-zone", filename);

        const attachZone = this.page.locator(`div[id="FeatureImageZone"]`);
        await this.tester.dragAndDropFile(attachZone, filePath);
        this.filePath = filePath;
    }

    async setAttachment(filePath: string): Promise<void> {
        const attachZone = this.page.locator("#FileZone");
        await this.tester.dragAndDropFile(attachZone, filePath);
        this.filePath = filePath;
    }

    /**
     * see feature image
     * @param filename - The filename to see
     */
    async seeFeatureImage(filename: string): Promise<void> {
        await expect(this.page.locator("#featureImage-zone")).toHaveValue(filename);
    }

    /**
     * fill topic
     * @param topic - The topic to fill
     */
    async fillTopic(topic: string): Promise<void> {
        await this.page.locator("input#news-topics-selectized").click();
        await this.page.locator(`div.option:has-text("${topic}")`).click();
        // await this.page.locator("div.option").click();
    }

    /**
     * see topic
     * @param topic - The topic to see
     */
    async seeTopic(topic: string): Promise<void> {
        await expect(this.page.locator("div.item")).toHaveText(topic);
    }

    /**
     * this is to click an audience type: Campus, Teams, Classes, Roles etc
     * @param audienceBrick - The audience brick
     */
    async selectAudienceOption(audienceBrick: string): Promise<void> {
        await this.page.locator("button.button.icon-drag-drop").click();
        await expect(this.page.locator('div[class="reveal-modal no-pad open"]')).toBeVisible();
        await this.page.locator(`button.button-block:has-text("${audienceBrick}")`).click();
        await expect(this.page.locator('label[for="audience-list-input-IjEi"]')).toBeVisible();
        // await this.page.locator(`label[for="audience-list-input-IjEi"]:has-text("${audienceType}")`).click();
        // await this.page.locator("button.button.submit").first().click();
        // await this.page.waitForTimeout(2000);
    }

    /**
     * fill roles
     * @param audience - tick a checkbox in the Select Audience: Campuses, Roles, Teams, HOuses, Year Levels
     */
    async checkboxAudience(audience: string): Promise<void> {
        // await this.page.locator("button.button.icon-drag-drop").click();
        // await expect(this.page.locator('div[class="reveal-modal no-pad open"]')).toBeVisible();
        // await this.page.locator(`button.button-block:has-text("Roles")`).click();
        await this.page.locator(`div.checklist-wrapper:has-text("${audience}")`).click();
        await this.page.locator("button.button.submit").first().click();
        await this.page.waitForTimeout(2000);
    }

    /**
     * see roles
     * @param role - The roles that was selected on the modal is presented in the news form
     */
    async seeAudience(role: string): Promise<void> {
        await expect(this.page.locator(`h4[title="${role}"]`)).toHaveText(role);
    }

    /**
     * fill users by typing in the input field
     * @param user - tick a checkbox in the Select Audience: Campuses, Roles, Teams, HOuses, Year Levels
     */
    async fillUser(user: string): Promise<void> {
        await this.page.locator(`input[id="audience-search-selectized"]`).fill(user);
        await this.page.keyboard.press("Enter");
        //     await this.page.locator("input#audience-search-selectized").press("Enter");
    }

    /**
     * see users
     * @param user - The users that was selected on the modal is presented in the news form
     */
    async seeUser(user: string): Promise<void> {
        await expect(this.page.locator(`div.h4`)).toHaveText(user);
    }

    /**
     * create composite audience
     * @param dropdown1 - The checkboxes to tick
     * @param dropdown2 - The checkboxes to tick
     * @param audienceType1 - The first audience type
     * @param audienceType2 - The second audience type
     */
    async createCompositeAudience(
        dropdown1: string,
        dropdown2: string,
        audienceType1: string,
        audienceType2: string,
    ): Promise<void> {
        await this.page.locator("button.button.icon-drag-drop").click();
        await expect(this.page.locator('div[class="reveal-modal no-pad open"]')).toBeVisible();
        await this.page.locator("a:has-text('Create Composite Audience')").click();
        await this.page.locator(`select.option:has-text(${dropdown1})`).click();
        await this.page.locator(`label:has-text(${audienceType1})`).click();
        await this.page.locator(`select.option:has-text(${dropdown2})`).click();
        await this.page.locator(`label:has-text(${audienceType2})`).click();
        await this.page.locator("button.button.submit").click();
        await this.page.waitForTimeout(2000);
    }

    /**
     * fill publish date
     * @param publishDate - The publish date
     */
    async fillPublishDate(publishDate: Date): Promise<void> {
        // await this.page.locator("input#publishAt").fill(publishDate);

        await this.page.fill("input#publishAt", Common.formatDate(publishDate, false));
    }

    /**
     * see publish date
     * @param publishDate - The publish date
     */
    async seePublishDate(publishDate: string): Promise<void> {
        await expect(this.page.locator("input#publishAt")).toContainText(publishDate);
    }

    /**
     * fill expiry date
     * @param expiryDate - The expiry date
     */
    async fillExpiryDate(expiryDate: Date): Promise<void> {
        // await this.page.locator("input#expiresAt").fill(expiryDate);

        await this.page.fill("input#expiresAt", Common.formatDate(expiryDate, false));
    }

    /**
     * see expiry date
     * @param expiryDate - The expiry date
     */
    async seeExpiryDate(expiryDate: string): Promise<void> {
        await expect(this.page.locator("input#expiresAt")).toContainText(expiryDate);
    }

    /**
     * untick the checkboxes
     * @param title - The checkboxes to untick
     */
    async removeAudience(title: string): Promise<void> {
        await this.page.locator(`a[title="Remove ${title} from audience"]`).click();
    }
}
