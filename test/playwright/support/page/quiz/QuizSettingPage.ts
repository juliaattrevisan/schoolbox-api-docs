import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class QuizSettingsPage {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/folders/{class_id}/assessments/new?type=quiz";

    static readonly SELECTOR_TITLE = "input#title";

    static readonly DATA_TEST_BUTTON_CREATE = "assessment-form-button-submit-Create";

    // Constants for data-test selectors
    static readonly SELECTOR_FORM = "assessment-form";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Sets title
     * @param title - The title to set
     */
    async setTitle(title: string): Promise<void> {
        await this.page.locator(QuizSettingsPage.SELECTOR_TITLE).fill(title);
    }

    /**
     * Submits this form to create a quiz
     * @param quizTitle - The title of the quiz
     */
    async create(quizTitle: string): Promise<void> {
        await this.page.getByTestId(QuizSettingsPage.DATA_TEST_BUTTON_CREATE).click();
        await expect(this.page.locator("#container")).toContainText(`Modify ${quizTitle}`);
    }
}
