import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";

/*
 * Page object for the Markbook page
 */

export default class MarkbookPage {
    private page: Page;

    static readonly URL = "/homepage/{class_id}";

    static readonly REPORT_CONTENT = "#report_content";

    static readonly DATA_TEST_MARKBOOK_SHOW_DISPLAY_MARKS = "markbook-show-display-marks";

    static readonly DATA_TEST_MARKBOOK_ASSESSMENT = "markbook-assessment";

    static readonly DATA_TEST_MARKBOOK_CELL = "markbook-cell-[ASSESSMENT_ID]-[STUDENT_ID]"; // Replace [ASSESSMENT_ID] and [STUDENT ID] with the actual IDs

    static readonly SELECTOR_MARKBOOK_CELL_DISPLAY_MARK = "[data-learner-mark]";

    static readonly SELECTOR_MARKBOOK_CELL_INPUT_MARK = "[data-instructor-mark]";

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
        await expect(this.page.locator(`h1:has-text("Class Markbook for Demo Class Page 2")`)).toBeVisible();
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
        const assessments = this.page.getByTestId(MarkbookPage.DATA_TEST_MARKBOOK_ASSESSMENT);
        await expect(assessments.locator(`a:has-text("${title}")`)).toBeVisible();
    }

    /**
     * Get an assessment ID by its title
     * @param title - The title of the assessment
     * @returns The ID of the assessment
     */
    async getAssessmentId(title: string): Promise<string> {
        const assessments = this.page.getByTestId(MarkbookPage.DATA_TEST_MARKBOOK_ASSESSMENT);
        const assessment = assessments.filter({ hasText: title });
        const assessmentId = await assessment.getAttribute("data-assessment");

        if (assessmentId === null) {
            expect(assessmentId).not.toBeNull();
            throw new Error(`Unable to find assessment ID for title: ${title}`);
        }

        return assessmentId;
    }

    /**
     * Verifies the given name exists on the markbook page
     * @param name - The name of the participant
     */
    async canSeeParticipant(name: string): Promise<void> {
        await expect(this.page.locator(`a[title="View Profile"]:has-text("${name}")`)).toBeVisible();
    }

    /**
     * Verifies the given name does not exist on the markbook page
     * @param name - The name of the participant
     */
    async cantSeeParticipant(name: string): Promise<void> {
        await expect(this.page.locator(`a[title="View Profile"]:has-text("${name}")`)).not.toBeVisible();
    }

    /**
     * Locates a cell for a given student and assessment in the markbook
     * @param studentId - The ID of the student
     * @param assessmentId - The ID of the assessment
     * @returns A locator for the cell
     */
    getCell(studentId: string | number, assessmentId: string | number): Locator {
        return this.page.getByTestId(
            MarkbookPage.DATA_TEST_MARKBOOK_CELL.replace("[STUDENT_ID]", studentId.toString()).replace(
                "[ASSESSMENT_ID]",
                assessmentId.toString(),
            ),
        );
    }

    /**
     * Turns ON the "Show Display Marks" checkbox
     */
    async showDisplayMarks(): Promise<void> {
        await this.page.getByTestId(MarkbookPage.DATA_TEST_MARKBOOK_SHOW_DISPLAY_MARKS).check();
    }

    /**
     * Turns OFF the "Show Display Marks" checkbox
     */
    async showInputMarks(): Promise<void> {
        await this.page.getByTestId(MarkbookPage.DATA_TEST_MARKBOOK_SHOW_DISPLAY_MARKS).uncheck();
    }

    /**
     * Verifies a given display mark exists in a given cell
     * "Show Display Marks" checkbox should be ON for this to work
     * @param studentId - The ID of the student
     * @param assessmentId - The ID of the assessment
     * @param mark - The display mark to check
     */
    async validateDisplayMark(
        studentId: string | number,
        assessmentId: string | number,
        mark: string | number,
    ): Promise<void> {
        const cell = this.getCell(studentId, assessmentId);
        await expect(cell.locator(MarkbookPage.SELECTOR_MARKBOOK_CELL_DISPLAY_MARK)).toHaveText(mark.toString());
    }

    /**
     * Verifies a given input mark exists in a given cell
     * "Show Display Marks" checkbox should be OFF for this to work
     * @param studentId - The ID of the student
     * @param assessmentId - The ID of the assessment
     * @param mark - The input mark to check
     */
    async validateInputMark(
        studentId: string | number,
        assessmentId: string | number,
        mark: string | number,
    ): Promise<void> {
        const cell = this.getCell(studentId, assessmentId);
        await expect(cell.locator(MarkbookPage.SELECTOR_MARKBOOK_CELL_INPUT_MARK)).toHaveText(mark.toString());
    }
}
