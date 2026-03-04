import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";

/*
 * Page object for the Markbook page
 */

export default class MarkbookPage {
    private page: Page;

    static readonly URL = "/homepage/{class_id}";

    static readonly URL_COURSE_MARKBOOK = "/learning/courseMarkbook/{course_id}";

    static readonly URL_CLASS_MARKBOOK = "/learning/markbook/class/{class_id}";

    static readonly DATA_TEST_MARKBOOK_SHOW_DISPLAY_MARKS = "markbook-show-display-marks";

    static readonly DATA_TEST_MARKBOOK_ASSESSMENT = "markbook-assessment-[ASSESSMENT_ID]"; // Replace [ASSESSMENT_ID] with the actual ID

    static readonly DATA_TEST_MARKBOOK_CELL = "markbook-cell-[ASSESSMENT_ID]-[STUDENT_ID]"; // Replace [ASSESSMENT_ID] and [STUDENT ID] with the actual IDs

    static readonly DATA_TEST_MARKBOOK_CELL_DISPLAY_MARK = "markbook-learner-mark";

    static readonly DATA_TEST_MARKBOOK_CELL_INPUT_MARK = "markbook-instructor-mark";

    static readonly DATA_TEST_MARKBOOK_ASSESSMENT_DROPDOWN = "markbook-assessment-dropdown-[ASSESSMENT_ID]-[ITEM]"; // Replace [ASSESSMENT_ID] with the actual ID and [ITEM] with the item in the list

    static readonly SELECTOR_SHOW_DRAFTS_CHECKBOX = `label[for="show-drafts"]`;

    static readonly DATA_TEST_MARKBOOK_CLASS_AVERAGE = "markbook-class-average-expander";

    // visibility constants
    static readonly VISIBILITY_STAFF_ONLY = 0;

    static readonly VISIBILITY_STAFF_AND_STUDENT = 1;

    static readonly VISIBILITY_ALL = 2;

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * navigates to the class homepage url
     * @param classId - The class id
     */
    async goto(classId: string): Promise<void> {
        await this.page.goto(MarkbookPage.URL.replace("{class_id}", classId));
    }

    /**
     * navigates to the course mark-book page url
     * @param courseId - The course id
     */
    async gotoCourseMarkbook(courseId: string): Promise<void> {
        await this.page.goto(MarkbookPage.URL_COURSE_MARKBOOK.replace("{course_id}", courseId));
    }

    /**
     * navigates to the class mark-book page url
     * @param classId - The class id
     */
    async gotoClassMarkbook(classId: string): Promise<void> {
        await this.page.goto(MarkbookPage.URL_CLASS_MARKBOOK.replace("{class_id}", classId));
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
        item: "view" | "modify" | "modify-content" | "view-status" | "import-feedback",
    ): Promise<void> {
        const dataTestId = MarkbookPage.DATA_TEST_MARKBOOK_ASSESSMENT_DROPDOWN.replace(
            "[ASSESSMENT_ID]",
            assessmentId.toString(),
        ).replace("[ITEM]", item);

        const dropdownItem = this.page.getByTestId(dataTestId);
        await dropdownItem.locator("a").click();
    }

    /**
     * Click the publish grades & feedback button in the assessment dropdown
     * @param assessmentId - The ID of the assessment
     */
    async clickPublishGradesAndFeedback(assessmentId: string | number): Promise<void> {
        const dataTestId = MarkbookPage.DATA_TEST_MARKBOOK_ASSESSMENT_DROPDOWN.replace(
            "[ASSESSMENT_ID]",
            assessmentId.toString(),
        ).replace("[ITEM]", "publish");

        const dropdownItem = this.page.getByTestId(dataTestId);
        await dropdownItem.locator("button").click();
    }

    /**
     * Clicks publish all button in the Publish Drafts modal
     */
    async clickPublishAllInModal(): Promise<void> {
        await this.page.locator(`button[type="submit"]:has-text("Publish All")`).click();
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
     * Clicks a cell for a given student and assessment in the markbook
     * @param assessmentId - The ID of the assessment
     * @param studentId - The ID of the student
     */
    async markTask(assessmentId: string | number, studentId: string | number): Promise<void> {
        const markTask = this.page.getByTestId(
            MarkbookPage.DATA_TEST_MARKBOOK_CELL.replace("[STUDENT_ID]", studentId.toString()).replace(
                "[ASSESSMENT_ID]",
                assessmentId.toString(),
            ),
        );
        await markTask.click();
    }

    /**
     * Checks the task status is complete or incomplete
     * @param assessmentId - The ID of the assessment
     * @param studentId - The ID of the student
     * @param status - The status to check: 'Completed' or 'Incomplete'
     */
    async checkTaskStatus(
        assessmentId: string | number,
        studentId: string | number,
        status: "Completed" | "Incomplete",
    ): Promise<void> {
        const taskMarkTick = this.page.getByTestId(
            MarkbookPage.DATA_TEST_MARKBOOK_CELL.replace("[STUDENT_ID]", studentId.toString()).replace(
                "[ASSESSMENT_ID]",
                assessmentId.toString(),
            ),
        );
        await expect(taskMarkTick.locator("a")).toHaveAttribute("title", new RegExp(status));
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

    /**
     * click Show Drafts checkbox
     */
    async clickShowDraftsCheckbox(): Promise<void> {
        await this.page.locator(MarkbookPage.SELECTOR_SHOW_DRAFTS_CHECKBOX).click();
    }

    /**
     * update the activity weight in the class mark book page
     * verify the class average and student grade is updated based on new weight
     * able to verify one or more students grades
     * @param assessmentId - The ID of the assessment
     * @param weight - The new weight to set
     * @param studentIds - An array of student IDs to verify
     * @param expectedClassAverage - The expected class average after weight change
     * @param expectedStudentGrades - An array of expected student grades after weight change
     */
    async updateActivityWeightAndVerifyGrades(
        assessmentId: string | number,
        weight: string | number,
        studentIds: (string | number)[],
        expectedClassAverage: string,
        expectedStudentGrades: string[],
    ): Promise<void> {
        await this.page.getByTestId(MarkbookPage.DATA_TEST_MARKBOOK_CLASS_AVERAGE).click();
        const assessmentCell = this.page.locator(`td[data-assessment="${assessmentId}"]`).first();
        await assessmentCell.locator("input").first().fill(weight.toString());
        await this.page.keyboard.press("Enter");
        await this.page.locator(`div[data-test-is-success="true"]`).waitFor({ state: "visible" });

        // Reload the markbook to reflect changes
        await this.page.reload();

        // Verify class average
        await expect(this.page.getByTestId("class-average-mark-book-page")).toHaveText(expectedClassAverage);

        // check the grades by passing an array of 1 or more student ids
        for (let i = 0; i < studentIds.length; i += 1) {
            const studentId = studentIds[i];
            const expectedStudentGrade = expectedStudentGrades[i];
            await expect(this.page.locator(`a[href="/learning/grades/${studentId}/"]`)).toHaveText(
                expectedStudentGrade,
            );
        }
    }

    /**
     * Check the grade for one or more students in the mark book page
     * @param studentIds - An array of student IDs to verify
     * @param expectedStudentGrades - An array of expected student grades
     */
    async verifyStudentGrades(studentIds: (string | number)[], expectedStudentGrades: string[]): Promise<void> {
        for (let i = 0; i < studentIds.length; i += 1) {
            const studentId = studentIds[i];
            const expectedStudentGrade = expectedStudentGrades[i];
            await expect(this.page.locator(`a[href="/learning/grades/${studentId}/"]`)).toHaveText(
                expectedStudentGrade,
            );
        }
    }

    /**
     * Verify that the class overall average is as expected
     * @param expectedClassAverage - The expected class average
     */
    async verifyClassOverallAverage(expectedClassAverage: string): Promise<void> {
        await expect(this.page.getByTestId("class-average-mark-book-page")).toHaveText(expectedClassAverage);
    }

    /**
     * update weight given a list of assessment ids and a list of weights to set
     * @param assessmentIds - An array of assessment IDs
     * @param weights - An array of weights to set
     */
    async updateMultipleActivityWeights(
        assessmentIds: (string | number)[],
        weights: (string | number)[],
    ): Promise<void> {
        await this.page.getByTestId(MarkbookPage.DATA_TEST_MARKBOOK_CLASS_AVERAGE).click();
        for (let i = 0; i < assessmentIds.length; i += 1) {
            const assessmentId = assessmentIds[i];
            const weight = weights[i];
            const assessmentCell = this.page.locator(`td[data-assessment="${assessmentId}"]`).first();
            await assessmentCell.locator("input").first().fill(weight.toString());
            await this.page.keyboard.press("Enter");
            await this.page.locator(`div[data-test-is-success="true"]`).waitFor({ state: "visible" });
        }
        // Reload the mark book to reflect changes
        await this.page.reload();
    }

    /**
     * selects a mark visibility option for a list of assessments in an array
     * @param assessmentIds - An array of assessment IDs
     * @param visibilityOptions - An array of visibility options to select: '0 for Staff Only', '1 for Staff and Students', '2 for Staff, Students and their Parents'
     */
    async updateMultipleActivityMarkVisibilities(
        assessmentIds: (string | number)[],
        visibilityOptions: (string | number)[],
    ): Promise<void> {
        for (let i = 0; i < assessmentIds.length; i += 1) {
            const assessmentId = assessmentIds[i];
            const visibilityOption = visibilityOptions[i];
            // locate the assessment dropdown, click it and select an option
            const assessment = this.page.locator(`td[data-assessment="${assessmentId}"]`);
            await assessment.click();
            await assessment
                .locator('select[name="markVisibility"]')
                .selectOption({ value: visibilityOption.toString() });
            await this.page.locator(`div[data-test-is-success="true"]`).waitFor({ state: "visible" });
        }
    }

    /**
     * Checks the visibility option selected for the activities passed in the array
     * @param assessmentIds - An array of assessment IDs
     * @param visibilities - An array of visibilities to check
     */
    async checkVisibilityOptionsSelected(
        assessmentIds: (string | number)[],
        visibilities: (string | number)[],
    ): Promise<void> {
        for (let i = 0; i < assessmentIds.length; i += 1) {
            const assessmentId = assessmentIds[i];
            const visibility = visibilities[i];
            const assessment = this.page.locator(`td[data-assessment="${assessmentId}"]`);
            const selectedValue = assessment.locator('select[name="markVisibility"]');
            await expect(selectedValue).toHaveValue(visibility.toString());
        }
    }

    /**
     * locates the cell for a given student and assessment
     * check whether or not the progress-tick-form is visible or not
     * @param studentId - The ID of the student
     * @param assessmentId - The ID of the assessment
     * @param shouldBeVisible - Whether the tick field should be visible or not: pass true to check it is visible and false fo check it is not visible
     */
    async checkProgressTickVisibility(
        studentId: string | number,
        assessmentId: string | number,
        shouldBeVisible: boolean,
    ): Promise<void> {
        const cell = this.getCell(studentId, assessmentId);
        const progressTick = cell.locator("div.progress-tick-form");
        if (shouldBeVisible) {
            await expect(progressTick).toBeVisible();
        } else {
            await expect(progressTick).not.toBeVisible();
        }
    }

}
