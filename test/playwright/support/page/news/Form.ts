import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";
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

    static readonly ARTICLE_HEADLINE = "#news-title";

    static readonly DATA_TEST_ARTICLE_BODY = "news-article-form-body";

    static readonly DATA_TEST_ARTICLE_BLURB = "news-article-form-blurb";

    static readonly FORM_CREATE = "form#createForm";

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
        await expect(this.page.locator(Form.ARTICLE_HEADLINE)).toHaveValue(headline);
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
        await expect(this.page.locator(`div[class="ck ck-editor__main"]`).filter({ hasText: headline })).toBeVisible();
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
        await expect(this.page.locator(`div[class="ck ck-editor__main"]`).filter({ hasText: blurb })).toBeVisible();
    }

    /**
     * fill feature image
     * @param filePath - The filename to fill
     */
    async attachFeatureImage(filePath: string): Promise<void> {
        const attachZone = this.page.locator("#featureImage-zone");
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
     * @param imageHash - The image hash to see
     */
    async seeFeatureImage(filename: string, imageHash: string): Promise<void> {
        await expect(this.page.locator(`a[data-plugin="preview"]`)).toContainText(filename);
        await expect(this.page.locator(`img[src*="/storage/image.php?hash=${imageHash}&size=square64"]`)).toBeVisible();
    }

    /**
     * fill topic
     * @param topic - The topic to fill
     */
    async fillTopic(topic: string): Promise<void> {
        await this.page.locator("input#news-topics-selectized").click();
        await this.page.locator(`div.option:has-text("${topic}")`).click();
    }

    /**
     * see topic
     * @param topic - The topic to see
     */
    async seeTopic(topic: string): Promise<void> {
        await expect(this.page.locator("div.item")).toContainText(topic);
    }

    /**
     * this is to click an audience type: Campus, Teams, Classes, Roles etc
     * @param audienceBrick - The audience brick
     */
    async selectAudienceOption(audienceBrick: string): Promise<void> {
        await this.page.locator("button.button.icon-drag-drop").click();
        await expect(this.page.locator('div[class="reveal-modal no-pad open"]')).toBeVisible();
        await this.page.locator(`button.button-block:has-text("${audienceBrick}")`).click();
        // await expect(this.page.locator('label[for="audience-list-input-IjEi"]')).toBeVisible();
        await expect(this.page.locator('div[class="checklist-wrapper"]').first()).toBeVisible();
    }

    /**
     * fill roles
     * @param audience - tick a checkbox in the Select Audience via bricks: Campuses, Roles, Teams, HOuses, Year Levels
     */
    async checkboxAudience(audience: string): Promise<void> {
        await this.page.locator(`div.checklist-wrapper:has-text("${audience}")`).click();
    }

    /**
     * fill class/group members and parents of students checkbox
     * @param audience - tick a checkbox: Class/Group Members (users) and Parents of Students(parents)
     */
    async selectIncludeUsers(audience: string): Promise<void> {
        await this.page.locator(`label[for="audience-select-${audience}"]`).click();
    }

    /**
     * click Add Audience in the modal
     */
    async clickAddAudience(): Promise<void> {
        await this.page.locator("button.button.submit").first().click();
        await this.waitForDraftToast();
    }

    /**
     * see roles
     * @param role - The roles that was selected on the modal is presented in the news form
     */
    async seeAudience(role: string): Promise<void> {
        await expect(this.page.locator(`h4[title="${role}"]`)).toHaveText(role);
    }

    /**
     * see audience meta data - eg: Inc. parents of studenrs, Class/Group Members
     * @param meta - The meta data of the audience
     */
    async seeAudienceMetaData(meta: string): Promise<void> {
        await expect(
            this.page.locator(
                'ul[class="grid small-block-grid-1 medium-block-grid-2 large-block-grid-4 audience-grid"]',
            ),
        ).toContainText(meta);
    }

    /**
     * fill users by typing in the input field
     * @param user - tick a checkbox in the Select Audience: Campuses, Roles, Teams, HOuses, Year Levels
     */
    async fillUser(user: string): Promise<void> {
        await this.page.locator(`input[id="audience-search-selectized"]`).click();
        await this.page.keyboard.type(user, { delay: 500 });
        await this.page.locator("[data-selectable]").first().filter({ hasText: user }).click();
    }

    /**
     * see users
     * @param user - The users that was selected on the modal is presented in the news form
     */
    async seeUser(user: string): Promise<void> {
        await this.page.locator(`h4[title="${user}"]`).first().filter({ hasText: user }).isVisible();
    }

    /**
     * create composite audience
     * @param dropdown1 - The checkboxes to tick
     * @param audienceType1 - The first audience type
     * @param dropdown2 - The checkboxes to tick
     * @param audienceType2 - The second audience type
     */
    async createCompositeAudience(
        dropdown1: string,
        audienceType1: Array<string>,
        dropdown2: string,
        audienceType2: Array<string>,
    ): Promise<void> {
        await this.page.locator("button.button.icon-drag-drop").click();
        await expect(this.page.locator('div[class="reveal-modal no-pad open"]')).toBeVisible();
        await this.page.locator("a:has-text('Create Composite Audience')").click();
        // await this.page.waitForTimeout(2000);
        await this.page.locator("legend").filter({ hasText: "Audience Type 1:" }).getByRole("combobox").click();
        await this.page.getByTestId("news-item-audience-select").first().selectOption({ label: dropdown1 });
        await this.tickCheckboxes(audienceType1);

        await this.page.locator("legend").filter({ hasText: "Audience Type 2:" }).getByRole("combobox").click();
        await this.page.getByTestId("news-item-audience-select").last().selectOption({ label: dropdown2 });
        await this.tickCheckboxes(audienceType2);

        await this.page.locator("button.button.submit").last().click();
    }

    /**
     * tick the checkboxes
     * @param checkboxes - The checkboxes to tick
     */
    async tickCheckboxes(checkboxes: Array<string>): Promise<void> {
        const checkbox = checkboxes.pop();
        if (checkbox) {
            await this.page.locator(`label[for="${checkbox}"]`).click();

            await this.tickCheckboxes(checkboxes);
        }
    }

    /**
     * fill publish date
     * @param publishDate - The publish date
     */
    async fillPublishDate(publishDate: Date): Promise<void> {
        await this.page.locator("input#publishAt").fill(Common.formatDate(publishDate, true));
    }

    /**
     * see publish date
     * @param publishDate - The publish date
     */
    async seePublishDate(publishDate: Date): Promise<void> {
        await expect(this.page.locator("input#publishAt")).toHaveValue(Common.formatDate(publishDate, true));
    }

    /**
     * fill expiry date
     * @param expiryDate - The expiry date
     */
    async fillExpiryDate(expiryDate: Date): Promise<void> {
        await this.page.locator("input#expiresAt").fill(Common.formatDate(expiryDate, true));
    }

    /**
     * see expiry date
     * @param expiryDate - The expiry date
     */
    async seeExpiryDate(expiryDate: Date): Promise<void> {
        await expect(this.page.locator("input#expiresAt")).toHaveValue(Common.formatDate(expiryDate, true));
    }

    /**
     * untick the checkboxes
     * @param title - The checkboxes to untick
     */
    async removeAudience(title: string): Promise<void> {
        await this.page.locator(`a[title="Remove ${title} from audience"]`).click();
    }

    /**
     * wait the toast to be presented  to ensure the draft is created
     */
    async waitForDraftToast(): Promise<void> {
        await expect(this.page.locator("#saveDraft")).toBeVisible({ timeout: 3000 });
        await expect(this.page.locator("#saveDraft")).toContainText("Saved");
    }
}
