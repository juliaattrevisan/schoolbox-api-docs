import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";

export default class GradesPage {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/grades/{id}";

    static readonly DATA_TEST_GRADES_SUBJECT = "grades-subject";

    static readonly DATA_TEST_GRADES_SUBJECT_HEADING = "grades-subject-heading";

    static readonly DATA_TEST_JELLY_TOT = "sbx-pop-out-header-options-button";

    static readonly SELECTOR_VIEW_INSTRUCTOR_GRADES = '[data-has-student-view="student"]';

    static readonly SELECTOR_VIEW_STUDENT_GRADES = '[data-has-student-view="staff"]';

    static readonly SELECTOR_ASSESSMENT = ".activity-list li.assessment";

    static readonly SELECTOR_HAS_STUDENT_VIEW_META = "[data-has-student-view-meta]";

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

    /**
     * click the jelly tot
     */
    async clickJellyTot(): Promise<void> {
        await this.page.getByTestId(GradesPage.DATA_TEST_JELLY_TOT).click();
    }

    /**
     * Change the current grades visibility to "View Instructor Grades"
     */
    async viewInstructorGrades(): Promise<void> {
        await this.clickJellyTot();
        await this.page.locator(GradesPage.SELECTOR_VIEW_INSTRUCTOR_GRADES).click();
    }

    /**
     * Change the current grades visibility to "View Student Grades"
     */
    async viewStudentGrades(): Promise<void> {
        await this.clickJellyTot();
        await this.page.locator(GradesPage.SELECTOR_VIEW_STUDENT_GRADES).click();
        await this.clickJellyTot();
    }

    /**
     * Check the current grades visibility
     * @param expectedVisibility - The expected visibility
     */
    async expectGradesVisibility(expectedVisibility: "student" | "instructor"): Promise<void> {
        const meta = this.page.locator(GradesPage.SELECTOR_HAS_STUDENT_VIEW_META);

        await expect(meta).toContainText(
            expectedVisibility === "student" ? "Viewing student grades" : "Viewing instructor grades",
        );
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
