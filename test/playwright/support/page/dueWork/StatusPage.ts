import type { Page} from "@playwright/test";
import { expect } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class StatusPage {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/assessments/{assessment_id}/status";

    static readonly SELECTOR_MARK = "input[name='mark']";

    static readonly SELECTOR_COMMENT = "textarea[name='comment']";

    static readonly SELECTOR_BUTTON_PUBLISH = "a[data-draft='publish']";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Go to the status page for a given assessment
     * @param assessmentId - The assessment ID
     */
    async goto(assessmentId: number): Promise<void> {
        const targetUrl = StatusPage.URL.replace("{assessment_id}", assessmentId.toString());
        await this.page.goto(targetUrl);
    }

    /**
     * Gets selector for a given student
     * @param studentId - The student ID
     * @param fieldSelector - The field selector
     * @returns - The generated selector
     */
    static getStatusSelector(studentId: number, fieldSelector: string): string {
        return `#assessment-status li[data-participant="${studentId}"] ${fieldSelector}`;
    }

    /**
     * Publishes feedback for the student on status page
     * @param studentId - The student ID
     * @param grade - The grade to give
     * @param comment - The comment to leave
     */
    async gradeSubmission(studentId: number, grade: number, comment: string): Promise<void> {
        await this.page
            .locator(StatusPage.getStatusSelector(studentId, StatusPage.SELECTOR_MARK))
            .fill(grade.toString());
        await this.page.locator(StatusPage.getStatusSelector(studentId, StatusPage.SELECTOR_COMMENT)).click();
        await this.page.keyboard.type(comment);

        const publishButton = this.page.locator(
            StatusPage.getStatusSelector(studentId, StatusPage.SELECTOR_BUTTON_PUBLISH),
        );
        await expect(publishButton).toBeVisible();
        await this.page.waitForTimeout(2000); // Wait for auto-save to finish
        await publishButton.click();
        await expect(publishButton).not.toBeVisible();
    }
}
