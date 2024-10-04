import { Page, expect } from "@playwright/test";

/*
 * Page object for the Markbook page
 */

export default class MarkbookPage {
    private page: Page;

    static readonly REPORT_CONTENT = "#report_content";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to the markbook page
     * @param classId - The ID of the class
     */
    async open(classId: number): Promise<void> {
        // back to homepage
        await this.page.goto(`/homepage/${classId}`);
        // Jelly tot
        await this.page.click("[data-test=sbx-pop-out-tab-control] [data-test=sbx-pop-out-tab-control-button]");
        // Markbook
        await this.page.click("a.icon-curriculum");
        await this.page.click('li[data-component="12"]');
        await this.page.click('label[for="filter_selection_12_1"]');
        await this.page.click('li[data-component="10"]');
        await this.page.click('label[for="filter_selection_10_open"]');
        await this.page.click('li[data-component="10"]');
        await this.page.click("input#submit_button");
    }

    /**
     * Verifies the given assessment title exists on the markbook page
     * @param title - The title of the assessment
     */
    async canSeeAssessment(title: string): Promise<void> {
        await this.page.locator(`a:has-text("${title}")`).isVisible({ timeout: 30000 });
    }

    /**
     * Verifies the given name exists on the markbook page
     * @param name - The name of the participant
     */
    async canSeeParticipant(name: string): Promise<void> {
        await this.page.locator(`a:has-text("${name}")`).isVisible({ timeout: 30000 });
    }

    /**
     * Verifies the given name does not exist on the markbook page
     * @param name - The name of the participant
     */
    async cantSeeParticipant(name: string): Promise<void> {
        await expect(this.page.locator(`a:has-text("${name}")`)).not.toBeVisible({ timeout: 30000 });
    }
}
