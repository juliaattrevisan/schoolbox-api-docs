/**
 * Utility class for student participation page actions
 * Handles exclusion and inclusion of students in assessment participation
 */

import type { Locator, Page } from "@playwright/test";
import { expect } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import Common from "@schoolboxTest/playwright/support/page/Common";

export default class StudentParticipation {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL_PARTICIPATION = "/learning/assessments/{activityId}/participation";

    static readonly URL_CLASS_MARKBOOK = "/learning/markbook/class/83?1=&5=&8=&10=&11=&12=";

    static readonly URL_COURSE_MARKBOOK = "/learning/courseMarkbook/6?1=&5=&8[15]=1&10=&11=&12=&14[83]=1";

    static readonly DATA_TEST_POP_OUT_PARTICIPANT_BUTTON = "sbx-pop-out-participant-{userId}-button";

    static readonly DATA_TEST_MARKBOOK_CELL = "markbook-cell-{activityId}-{userId}";

    static readonly DATA_TEST_STUDENT_PROFILE_FLAGS = "pastoral-flags-{studentId}";

    static readonly DATA_TEST_PROFILE_FLAG = "pastoral-flags-{typeId}-{subtypeId}";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigate to the student participation page
     * @param activityId - The ID of the activity to view participation for
     */
    async goto(activityId: string): Promise<void> {
        this.tester.isGoingTo("Navigate to student participation page");
        await this.page.goto(StudentParticipation.URL_PARTICIPATION.replace("{activityId}", activityId));
    }
    /**
     * click the student participation 3-dot menu for a specific student
     * @param userId - The user ID of the student
     */
    async clickStudentDotMenu(userId: string): Promise<void> {
        this.tester.isGoingTo(`Click the Show Actions button for participant ${userId}`);
        await this.page
            .getByTestId(StudentParticipation.DATA_TEST_POP_OUT_PARTICIPANT_BUTTON.replace("{userId}", userId))
            .click();
    }

    /**
     * click one of the options under the 3-dot menu for a specific student
     * @param button - The button to click for the specific student's action: modify, exclude, etc
     * @param userId - The user ID of the student
     */
    async clickStudentActionButton(button: string, userId: string): Promise<void> {
        this.tester.isGoingTo(`Click the ${button} under the 3-dot menu for participant ${userId}`);
        await this.page.locator(`[id="participant-${userId}"]`).getByRole("button", { name: button }).click();
    }

    /**
     * choose one of the activities to create for a specific student
     * @param activityType - The type of activity to create: task, dueWork, quiz
     */
    async chooseActivityType(activityType: string): Promise<void> {
        this.tester.isGoingTo(`Choose ${activityType} as the activity type to create`);
        await this.page.locator(`label[for="${activityType}"]`).click();
    }

    /**
     * check whether or not the custom activity is presented in the student participation table for the specific student
     * @param studentName - The name of the student for verification
     * @param activityTitle - The title of the activity to check in the table
     * @param isPresented - Whether the activity is expected to be presented or not in the table
     * default value is true for checking the activity is presented in the table
     * set to false for checking the activity is not presented in the table
     * the function will check the activity is presented when isPresented is true, and check the activity is not presented when isPresented is false
     * this is to avoid code duplication for checking both presence and absence of the activity in the table
     * example usage:
     * checkCustomActivityInTable(studentName, activityTitle); // checks the activity is presented in the table
     * checkCustomActivityInTable(studentName, activityTitle, false); // checks the activity is not presented in the table
     * @returns void
     */
    async checkCustomActivityInTable(
        studentName: string,
        activityTitle: string,
        isPresented: boolean = true,
    ): Promise<void> {
        this.tester.isGoingTo(
            `Verify the activity '${activityTitle}' is ${isPresented ? "shown" : "not shown"} in the participation table for participant ${studentName}`,
        );
        const studentRow = this.page.locator("tr").filter({ hasText: studentName });
        if (isPresented) {
            await expect(studentRow).toContainText(activityTitle);
        } else {
            await expect(studentRow).not.toContainText(activityTitle);
        }
    }

    /**
     * Exclude a student from participation
     * @param userId - The user ID of the student to exclude
     * @param studentName - The name of the student for verification
     * @param hasConfirmExclusion - Whether to confirm the exclusion in the modal
     */
    async excludeStudent(userId: string, studentName: string, hasConfirmExclusion: boolean): Promise<void> {
        this.tester.isGoingTo(`Click the Show Actions button for participant ${userId} (${studentName})`);
        await this.page
            .getByTestId(StudentParticipation.DATA_TEST_POP_OUT_PARTICIPANT_BUTTON.replace("{userId}", userId))
            .click();

        this.tester.isGoingTo("Click the Exclude button in the popup");
        await this.page.getByRole("button", { name: "Exclude" }).click();

        if (hasConfirmExclusion) {
            this.tester.isGoingTo("Confirm exclusion by clicking Yes in the modal");
            await this.page.getByRole("button", { name: "Yes" }).click();
        }

        this.tester.isGoingTo("Verify the student row shows 'Excluded'");
        const studentRow = this.page.locator("tr").filter({ hasText: studentName });
        await expect(studentRow).toContainText("Excluded");

        this.tester.isGoingTo("Verify success message is displayed");
        await expect(this.page.getByText("Student excluded successfully")).toBeVisible();
    }

    /**
     * Remove customisation (inclusion) for a student
     * @param userId - The user ID of the student to include
     * @param studentName - The name of the student for verification
     * @param customisationTitle - The modified title to verify removal
     */
    async removeCustomisation(userId: string, studentName: string, customisationTitle: string): Promise<void> {
        this.tester.isGoingTo("Verify student's row shows the modified customisation title");
        const studentRow = this.page.locator("tr").filter({ hasText: studentName });
        await expect(studentRow).toContainText(customisationTitle);

        this.tester.isGoingTo(`Click the Show Actions button for participant ${userId} (${studentName})`);
        await this.page
            .getByTestId(StudentParticipation.DATA_TEST_POP_OUT_PARTICIPANT_BUTTON.replace("{userId}", userId))
            .click();

        this.tester.isGoingTo("Click Remove Customisation button");
        await this.page.getByRole("button", { name: " Remove Customisation" }).click();

        this.tester.isGoingTo("Verify success message appears for removal");
        await expect(this.page.getByText("Customisation removed successfully")).toBeVisible();

        this.tester.isGoingTo("Verify student's row no longer shows the modified customisation title");
        await expect(studentRow).not.toContainText(customisationTitle);
    }

    /**
     * select a customisation in the Include customisation modal
     * @param titleIndex - The index of the customisation to select
     */
    async selectCustomisationInModal(titleIndex: number): Promise<void> {
        // When the Customisation select with the id activitySelect
        const customisationSelect = this.page.locator("select#activitySelect");

        // Select the option based on the provided index - the placeholder is index 0, so the first customisation option starts from index 1
        await customisationSelect.selectOption({ index: titleIndex });

        // In the modal select the button that includes the text 'Confirm'
        await this.page.getByTestId("cp-include-in-existing-modal-confirm-button").click();
    }

    /**
     * Clicks the Yes on the modal for including in existing customisation
     */
    async confirmIncludeCustomisation(): Promise<void> {
        // In the please confirm modal select the button that includes the text 'Yes'
        await this.page.getByTestId("cp-confirm-modify-modal-yes-button").click();
    }

    /**
     * Verify excluded class is present in class markbook
     * @param userId - The user ID of the student
     * @param activityId - The activity ID
     */
    async verifyClassMarkbookExcluded(userId: string, activityId: string): Promise<void> {
        this.tester.isGoingTo("Navigate to class markbook and verify excluded class is present");
        await this.page.goto(StudentParticipation.URL_CLASS_MARKBOOK);
        await expect(
            this.page.getByTestId(
                StudentParticipation.DATA_TEST_MARKBOOK_CELL.replace("{activityId}", activityId).replace(
                    "{userId}",
                    userId,
                ),
            ),
        ).toHaveClass(/excluded/);
    }

    /**
     * Verify excluded class is not present in class markbook
     * @param userId - The user ID of the student
     * @param activityId - The activity ID
     */
    async verifyClassMarkbookNotExcluded(userId: string, activityId: string): Promise<void> {
        this.tester.isGoingTo("Navigate to class markbook and verify cells no longer have excluded class");
        await this.page.goto(StudentParticipation.URL_CLASS_MARKBOOK);
        await expect(
            this.page.getByTestId(
                StudentParticipation.DATA_TEST_MARKBOOK_CELL.replace("{activityId}", activityId).replace(
                    "{userId}",
                    userId,
                ),
            ),
        ).not.toHaveClass(/excluded/);
    }

    /**
     * Verify excluded class is present in course markbook
     * @param userId - The user ID of the student
     * @param activityId - The activity ID
     */
    async verifyCourseMarkbookExcluded(userId: string, activityId: string): Promise<void> {
        this.tester.isGoingTo("Navigate to course markbook and verify excluded class is present");
        await this.page.goto(StudentParticipation.URL_COURSE_MARKBOOK);
        await expect(
            this.page.getByTestId(
                StudentParticipation.DATA_TEST_MARKBOOK_CELL.replace("{activityId}", activityId).replace(
                    "{userId}",
                    userId,
                ),
            ),
        ).toHaveClass(/excluded/);
    }

    /**
     * Verify excluded class is not present in course markbook
     * @param userId - The user ID of the student
     * @param activityId - The activity ID
     */
    async verifyCourseMarkbookNotExcluded(userId: string, activityId: string): Promise<void> {
        this.tester.isGoingTo("Navigate to course markbook and verify cells no longer have excluded class");
        await this.page.goto(StudentParticipation.URL_COURSE_MARKBOOK);
        await expect(
            this.page.getByTestId(
                StudentParticipation.DATA_TEST_MARKBOOK_CELL.replace("{activityId}", activityId).replace(
                    "{userId}",
                    userId,
                ),
            ),
        ).not.toHaveClass(/excluded/);
    }

    /**
     * Modify current activity for a student
     * @param userId - The user ID of the student
     * @param studentName - The name of the student for verification
     * @param newTitle - The new title for the activity
     * @param description - The description for the activity
     * @param dueDate - The due date for the activity
     */
    async modifyStudentActivity(
        userId: string,
        studentName: string,
        newTitle: string,
        description: string,
        dueDate: Date,
    ): Promise<void> {
        this.tester.isGoingTo(`Click the Show Actions button for participant ${userId} (${studentName})`);
        await this.page
            .getByTestId(StudentParticipation.DATA_TEST_POP_OUT_PARTICIPANT_BUTTON.replace("{userId}", userId))
            .click();

        this.tester.isGoingTo("Click the Modify button in the popup");
        await this.page.locator(`[id="participant-${userId}"]`).getByRole("button", { name: "Modify" }).click();

        this.tester.isGoingTo(`Add '${newTitle}' as the Title`);
        await this.page.getByLabel("Title").fill(newTitle);

        this.tester.isGoingTo(`Add '${description}' into the Description`);
        await this.tester.fillCKEditorById("plan-desc", description);

        this.tester.isGoingTo(`Add '${dueDate.toISOString()}' as the Due Date`);
        await this.page.getByLabel("Due Date").fill(Common.formatDate(dueDate, true));

        this.tester.isGoingTo("Select the Update button to save");
        await this.page.getByRole("button", { name: "Update" }).click();

        this.tester.isGoingTo(`Verify the text '${newTitle} (Modified activity)' is visible in the student row`);
        const studentRow = this.page.locator("tr").filter({ hasText: studentName });
        await expect(studentRow).toContainText(`${newTitle} (Modified activity)`);
    }

    /**
     * Verify due date text on student's individual assessment page
     * @param userId - The user ID of the student
     * @param assessmentId - The assessment ID
     * @param expectedText - The expected text to find in the due date span
     */
    async verifyDueDateText(userId: string, assessmentId: string, expectedText: string): Promise<void> {
        this.tester.isGoingTo(`Navigate to student's assessment page and verify due date text`);
        await this.page.goto(`/learning/assessments/${assessmentId}/${userId}`);

        this.tester.isGoingTo(`Verify the span within the calendar icon contains '${expectedText}'`);
        const dueElementText = this.page.locator("li.icon-calendar span span");
        await expect(dueElementText).toContainText("Due");
        const calendarElement = this.page.locator("li.icon-calendar span time").first();
        await expect(calendarElement).toContainText(expectedText);
    }

    /**
     * Verify due date span on assessment status page
     * @param assessmentId - The assessment ID
     * @param expectedDueDate - The expected due date text to find in a span
     */
    async verifyDueDateSpan(assessmentId: string, expectedDueDate: string): Promise<void> {
        this.tester.isGoingTo(`Navigate to assessment status page and verify due date span`);
        await this.page.goto(`/learning/assessments/${assessmentId}/status`);

        this.tester.isGoingTo(`Verify there is a span element that includes the set due date '${expectedDueDate}'`);
        const dueDateSpan = this.page.locator(`span:text-is("${expectedDueDate}")`);
        expect(dueDateSpan).toBeDefined();
    }

    /**
     * Returns a flag label locator
     * @param studentId - The student id
     * @param typeId - The pastoral type id
     * @param subtypeId - The pastoral subtype id
     * @returns - The locator for the flag label
     */
    getFlagLabel(studentId: number, typeId: number, subtypeId: number): Locator {
        const student = this.page.getByTestId(
            StudentParticipation.DATA_TEST_STUDENT_PROFILE_FLAGS.replace("{studentId}", studentId.toString()),
        );
        return student.getByTestId(
            StudentParticipation.DATA_TEST_PROFILE_FLAG.replace("{typeId}", typeId.toString()).replace(
                "{subtypeId}",
                subtypeId.toString(),
            ),
        );
    }
}
