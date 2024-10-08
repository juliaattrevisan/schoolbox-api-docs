import type { Page } from "@playwright/test";

export default class SectionForm {
    private page: Page;

    static readonly URL = "/survey/section/create/{assess_id}";

    static readonly FORM = "#createSection";

    static readonly SELECTOR_TITLE = `${SectionForm.FORM} input[name="title"]`;

    static readonly SELECTOR_BUTTON_CREATE = `${SectionForm.FORM} [type=submit][value="Create Section"]`;

    static readonly SELECTOR_SECTION_ID = 'input[name="section_id"]';

    static readonly SELECTOR_ASSESS_ID = 'input[name="assess_id"]';

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Generates route for this page.
     * @param assessId - The ID of the page
     */
    async goto(assessId: number): Promise<void> {
        await this.page.goto(SectionForm.generateRoute(assessId));
    }

    /**
     * Generates route for this page
     * @param assessId - The ID of the page
     * @returns - the URL for the page
     */
    public static generateRoute(assessId: number): string {
        return SectionForm.URL.replace("{assessId}", assessId.toString());
    }

    /**
     * Sets title
     * @param title - The title of the section
     */
    async setTitle(title: string): Promise<void> {
        await this.page.locator(`h2.subheader:has-text("CREATE SECTION")`).isVisible();
        await this.page.fill(SectionForm.SELECTOR_TITLE, title);
    }

    /**
     * Submits this form to create the section
     */
    async create(): Promise<void> {
        await this.page.click(SectionForm.SELECTOR_BUTTON_CREATE);
    }

    /**
     * Gets assess id
     * @returns - the assess ID
     */
    async getAssessId(): Promise<number> {
        const assessIdString = await this.page.locator(SectionForm.SELECTOR_ASSESS_ID).getAttribute("value");
        const assessId = Number(assessIdString);
        return assessId;
    }

    /**
     * Gets section id from current form
     * @returns - the section ID
     */
    async getSectionId(): Promise<number> {
        const sectionIdString = await this.page.locator(SectionForm.SELECTOR_SECTION_ID).getAttribute("value");
        const sectionId = Number(sectionIdString);
        return sectionId;
    }

    /**
     * Finishes modification
     * @param title - The title of the section
     */
    async finalize(title: string): Promise<void> {
        await this.page.locator(title).click();
        await this.page.waitForSelector("Finish Modifying");
        await this.page.click("Finish Modifying");
    }
}
