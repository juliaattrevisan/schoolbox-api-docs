import type { Page } from "@playwright/test";

export default class SummaryPage {
    private page: Page;

    static readonly URL = "/survey/summary/{assess_id}";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Generates route for this page.
     * @param assessId - The ID of the page
     */
    async goto(assessId: number): Promise<void> {
        await this.page.goto(SummaryPage.generateRoute(assessId));
    }

    /**
     * Navigates to summary page
     * @param assessId - The ID of the page
     * @returns - the URL for the page
     */
    public static generateRoute(assessId: number): string {
        return SummaryPage.URL.replace("{assess_id}", assessId.toString());
    }

    /**
     * Adds a question to Question Bank
     * @param questionId - The ID of the question
     * @param categoryId - The ID of the category
     */
    async addToQuestionBank(questionId: number, categoryId: number): Promise<void> {
        await this.page.click(`#question_${questionId} a.icon-download`);
        await this.page.waitForSelector("#question-bank");
        await this.page.click(`#node_${categoryId}_anchor`);
        await this.page.click('input[type=submit][value="Add To Question Bank"]');
        await this.page.waitForSelector("#bank-success");
        await this.page.reload();
    }

    /**
     * Clicks Add from Question bank for the given section
     * @param sectionId - The ID of the section
     */
    async clickAddFromQuestionBank(sectionId: number): Promise<void> {
        await this.page.click(`[data-test=sbx-pop-out-section${sectionId}-options]`);
        await this.page.click(`#section${sectionId}-options a.icon-download`);
        await this.page.locator(`h2.subheader:has-text("Categories")`).isVisible();
    }

    /**
     * Clicks New Question for the given section
     * @param sectionId - The ID of the section
     */
    async clickNewQuestion(sectionId: number): Promise<void> {
        await this.page.click(`[data-test=sbx-pop-out-section${sectionId}-options]`);
        await this.page.click(`#section${sectionId}-options a.icon-add`);
        await this.page.locator(`h2.subheader:has-text("Create Question")`).isVisible();
    }
}
