import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import AssessmentForm from "./AssessmentForm";

export default class AssessmentPage {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/assessments/{assessment_id}/{user_id}";

    static readonly DATATEST_LINK_JELLY_TOT = "sbx-pop-out-header-options";

    static readonly SELECTOR_LINK_MODIFY = "#header-options a.icon-modify";

    static readonly SELECTOR_LINK_STATUS = "#header-options a.icon-progress";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Go to the assessment page for a given assessment and user
     * @param assessmentId - The assessment ID
     * @param userId - The user ID
     */
    async goto(assessmentId: number, userId: number): Promise<void> {
        const targetUrl = AssessmentPage.URL.replace("{assessment_id}", assessmentId.toString()).replace(
            "{user_id}",
            userId.toString(),
        );
        await this.page.goto(targetUrl);
    }

    /**
     * Navigates to modify page
     */
    async clickModify(): Promise<void> {
        await this.page.getByTestId(AssessmentPage.DATATEST_LINK_JELLY_TOT).click();
        await this.page.locator(AssessmentPage.SELECTOR_LINK_MODIFY).click();
        await expect(this.page.getByTestId(AssessmentForm.SELECTOR_BUTTON_UPDATE)).toBeVisible();
    }

    /**
     * Navigates to inline grader for the student
     * @param student - The student's name
     * @param title - The assessment title
     */
    async openInlineGrader(student: string, title: string): Promise<void> {
        await this.page
            .locator('#content section[class="submission-history"]')
            .getByText(`Submission by ${student}`)
            .click();
        await this.page.waitForSelector(`text=Grading ${title}`);
    }

    /**
     * Navigates to duework status page
     */
    async openStatusPage(): Promise<void> {
        await this.page.getByTestId(AssessmentPage.DATATEST_LINK_JELLY_TOT).click();
        await this.page.locator(AssessmentPage.SELECTOR_LINK_STATUS).click();
    }
}
