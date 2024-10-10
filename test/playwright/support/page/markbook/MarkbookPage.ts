import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";

/*
 * Page object for the Markbook page
 */

export default class MarkbookPage {
    private page: Page;

    static readonly URL = "/homepage/{class_id}";

    static readonly REPORT_CONTENT = "#report_content";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to the markbook page
     * @param classId - The ID of the class
     */
    async open(classId: string): Promise<void> {
        // back to homepage, waiting so it does not attempt to navigate before the previous action is completed
        await this.page.waitForTimeout(2000);
        await this.page.goto(MarkbookPage.URL.replace("{class_id}", classId));

        await this.page.waitForTimeout(2000);
        // Jelly tot
        await this.page
            .locator("[data-test=sbx-pop-out-tab-control] [data-test=sbx-pop-out-tab-control-button]")
            .click();
        // Markbook
        await this.page.locator("a.icon-curriculum").first().click();
        await this.page.locator(`h1:has-text("Class Markbook for Demo Class Page 2")`).isVisible({ timeout: 30000 });
    }

    /**
     * clas smarkbook filter - select work type
     * @param workType - The work type to select: None 13, Assessment Task 1, Assignment 2, Homework3 etc
     */
    async selectWorkType(workType: number): Promise<void> {
        await this.page.getByTestId("sbx-pop-out-filter-component-12-button").click();
        await this.page.locator(`label[for="filter_selection_12_${workType}"]`).click();
    }

    /**
     * Selects the activity from the markbook page
     * @param status - the assessment status:  unpublished, open, closed, overdue. upcomiing
     */
    async selectStatus(status: string): Promise<void> {
        await this.page.locator('li[data-component="10"]').click();
        await this.page.locator(`label[for="filter_selection_10_${status}"]`).click();
        await this.page.locator('li[data-component="10"]').click();
    }

    /**
     * cliick Apply  button
     */
    async applyFilter(): Promise<void> {
        await this.page.locator("input#submit_button").click();
        // give time to load the data
        await this.page.waitForTimeout(2000);
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
        await this.page.locator(`a[title="View Profile"]:has-text("${name}")`).isVisible({ timeout: 30000 });
    }

    /**
     * Verifies the given name does not exist on the markbook page
     * @param name - The name of the participant
     */
    async cantSeeParticipant(name: string): Promise<void> {
        await expect(this.page.locator(`a[title="View Profile"]:has-text("${name}")`)).not.toBeVisible({
            timeout: 30000,
        });
    }
}
