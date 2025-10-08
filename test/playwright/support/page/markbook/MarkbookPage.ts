import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";

/*
 * Page object for the Markbook page
 */

export default class MarkbookPage {
    private page: Page;

    static readonly URL = "/homepage/{class_id}";

    static readonly DATA_TEST_MARKBOOK_SHOW_DISPLAY_MARKS = "markbook-show-display-marks";

    static readonly DATA_TEST_MARKBOOK_ASSESSMENT = "markbook-assessment-[ASSESSMENT_ID]"; // Replace [ASSESSMENT_ID] with the actual ID

    static readonly DATA_TEST_MARKBOOK_CELL = "markbook-cell-[ASSESSMENT_ID]-[STUDENT_ID]"; // Replace [ASSESSMENT_ID] and [STUDENT ID] with the actual IDs

    static readonly DATA_TEST_MARKBOOK_CELL_DISPLAY_MARK = "markbook-learner-mark";

    static readonly DATA_TEST_MARKBOOK_CELL_INPUT_MARK = "markbook-instructor-mark";

    static readonly DATA_TEST_MARKBOOK_ASSESSMENT_DROPDOWN = "markbook-assessment-dropdown-[ASSESSMENT_ID]-[ITEM]"; // Replace [ASSESSMENT_ID] with the actual ID and [ITEM] with the item in the list

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
     * class markbook filter - select work type
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
        const selectorRegex = new RegExp(MarkbookPage.DATA_TEST_MARKBOOK_ASSESSMENT.replace("[ASSESSMENT_ID]", "\\d+"));
        const assessments = this.page.getByTestId(selectorRegex);
        await expect(assessments.locator(`a:has-text("${title}")`)).toBeVisible();
    }

    /**
     * Get an assessment ID by its title
     * @param title - The title of the assessment
     * @returns The ID of the assessment
     */
    async getAssessmentId(title: string): Promise<string> {
        const selectorRegex = new RegExp(MarkbookPage.DATA_TEST_MARKBOOK_ASSESSMENT.replace("[ASSESSMENT_ID]", "\\d+"));
        const assessments = this.page.getByTestId(selectorRegex);
        const assessment = assessments.filter({ hasText: title });
        const assessmentDataTest = await assessment.getAttribute("data-test");

        if (assessmentDataTest === null) {
            expect(assessmentDataTest).not.toBeNull();
            throw new Error(`Unable to find assessment's data-test for title: ${title}`);
        }

        // Strip the assessment ID from the data-test attribute
        return assessmentDataTest.replace(
            MarkbookPage.DATA_TEST_MARKBOOK_ASSESSMENT.replace("[ASSESSMENT_ID]", ""),
            "",
        );
    }

    /**
     * Open the assessment title dropdown for a given assessment
     * @param assessmentId - The ID of the assessment
     */
    async openAssessmentDropdown(assessmentId: string | number): Promise<void> {
        const assessment = this.page.getByTestId(
            MarkbookPage.DATA_TEST_MARKBOOK_ASSESSMENT.replace("[ASSESSMENT_ID]", assessmentId.toString()),
        );
        await assessment.locator("a").click();
    }

    /**
     * Click an item in the assessment dropdown
     * @param assessmentId - The ID of the assessment
     * @param item - The item to click
     */
    async clickAssessmentDropdownItem(
        assessmentId: string | number,
        item: "view" | "modify" | "modify-content" | "view-status" | "import-feedback" | "publish",
    ): Promise<void> {
        const dataTestId = MarkbookPage.DATA_TEST_MARKBOOK_ASSESSMENT_DROPDOWN.replace(
            "[ASSESSMENT_ID]",
            assessmentId.toString(),
        ).replace("[ITEM]", item);

        const dropdownItem = this.page.getByTestId(dataTestId);
        await dropdownItem.locator("a").click();
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
        await expect(cell).toHaveText(mark.toString());
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
        await expect(cell).toHaveText(mark.toString());
    }
}
