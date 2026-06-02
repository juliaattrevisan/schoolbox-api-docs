import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";

export default class GradesPage {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/grades/{id}";

    static readonly DATA_TEST_GRADES_SUBJECT = "grades-subject";

    static readonly DATA_TEST_GRADES_SUBJECT_HEADING = "grades-subject-heading";

    static readonly SELECTOR_ASSESSMENT = ".activity-list li.assessment";

    static readonly SELECTOR_LEARNER_OR_INSTRUCTOR_MARK =
        "div[data-learner-mark]:visible, div[data-instructor-mark]:visible";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the grades page
     * @param id - The user id
     */
    async goto(id: number): Promise<void> {
        await this.page.goto(GradesPage.URL.replace("{id}", id.toString()));
    }

    /**
     * Click one of the subjects when on the summary grades page
     * @param subjectName - The name of the subject to click
     */
    async clickSubject(subjectName: string): Promise<void> {
        const subjects = this.page.getByTestId(GradesPage.DATA_TEST_GRADES_SUBJECT_HEADING);
        const subject = subjects.filter({ hasText: subjectName });
        await subject.click();
    }

    async viewAs(mode: "staff" | "student" | "parent"): Promise<void> {
        // The switcher is a radio group at the top-left of the page; selecting a mode navigates to it.
        await this.page.locator(`label[for="grades-view-mode-${mode}"]`).click();
    }

    /**
     * Check the current grades view mode is selected in the radio switcher.
     * @param mode - The expected grades view mode
     */
    async expectGradesViewMode(mode: "staff" | "student" | "parent"): Promise<void> {
        await expect(this.page.locator(`[data-grades-view-mode="${mode}"]`)).toBeChecked();
    }

    /**
     * Change the current grades visibility to staff view mode.
     */
    async viewInstructorGrades(): Promise<void> {
        await this.viewAs("staff");
    }

    /**
     * Change the current grades visibility to student view mode.
     */
    async viewStudentGrades(): Promise<void> {
        await this.viewAs("student");
    }

    /**
     * Check the current grades visibility
     * @param expectedVisibility - The expected visibility
     */
    async expectGradesVisibility(expectedVisibility: "student" | "instructor"): Promise<void> {
        await this.expectGradesViewMode(expectedVisibility === "student" ? "student" : "staff");
    }

    /**
     * Locate an assessment by its name
     * @param assessmentName - The name of the assessment
     * @returns - The assessment locator
     */
    locateAssessment(assessmentName: string): Locator {
        return this.page
            .locator(GradesPage.SELECTOR_ASSESSMENT)
            .filter({ has: this.page.locator("a").filter({ hasText: assessmentName }) });
    }

    /**
     * Expect an assessment to have a particular mark
     * @param assessmentName - The name of the assessment
     * @param mark - The expected mark
     */
    async expectAssessmentMark(assessmentName: string, mark: string | null): Promise<void> {
        const assessment = this.locateAssessment(assessmentName);
        // The mark will either be in [data-learner-mark] or [data-instructor-mark], one of which will be visible
        const markLocator = assessment.locator(GradesPage.SELECTOR_LEARNER_OR_INSTRUCTOR_MARK);

        if (mark === null) {
            await expect(markLocator).not.toBeVisible();
            return;
        }

        await expect(markLocator).toHaveText(mark);
    }
}
