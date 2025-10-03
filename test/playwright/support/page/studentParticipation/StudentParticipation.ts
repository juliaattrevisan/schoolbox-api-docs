/**
 * Utility class for student participation page actions
 * Handles exclusion and inclusion of students in assessment participation
 */

import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class StudentParticipation {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL_PARTICIPATION = "/learning/assessments/650/participation";

    static readonly URL_CLASS_MARKBOOK = "/learning/markbook/class/83?1=&5=&8=&10=&11=&12=";

    static readonly URL_COURSE_MARKBOOK = "/learning/courseMarkbook/6?1=&5=&8[15]=1&10=&11=&12=&14[83]=1";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigate to the student participation page
     */
    async goto(): Promise<void> {
        this.tester.isGoingTo("Navigate to student participation page");
        await this.page.goto(StudentParticipation.URL_PARTICIPATION);
        await expect(this.page).toHaveTitle(/Schoolbox \[vagrant\]/);
    }

    /**
     * Exclude a student from participation
     * @param userId - The user ID of the student to exclude
     * @param studentName - The name of the student for verification
     */
    async excludeStudent(userId: string, studentName: string): Promise<void> {
        this.tester.isGoingTo(`Click the Show Actions button for participant ${userId} (${studentName})`);
        await this.page.locator(`[data-test="sbx-pop-out-participant-${userId}-button"]`).click();

        this.tester.isGoingTo("Click the Exclude button in the popup");
        await this.page.getByRole("button", { name: "Exclude" }).click();

        this.tester.isGoingTo("Confirm exclusion by clicking Yes in the modal");
        await this.page.getByRole("button", { name: "Yes" }).click();

        this.tester.isGoingTo("Verify the student row shows 'Excluded' in the Customisation column");
        const studentRow = this.page.locator("tr").filter({ hasText: studentName });
        await expect(studentRow.locator("td").nth(3)).toContainText("Excluded");

        this.tester.isGoingTo("Verify success message is displayed");
        await expect(this.page.getByText("Student excluded successfully")).toBeVisible();
    }

    /**
     * Remove customisation (inclusion) for a student
     * @param userId - The user ID of the student to include
     * @param studentName - The name of the student for verification
     */
    async removeCustomisation(userId: string, studentName: string): Promise<void> {
        this.tester.isGoingTo(`Click the Show Actions button for participant ${userId} (${studentName})`);
        await this.page.locator(`[data-test="sbx-pop-out-participant-${userId}-button"]`).click();

        this.tester.isGoingTo("Click Remove Customisation button");
        await this.page.getByRole("button", { name: " Remove Customisation" }).click();

        this.tester.isGoingTo("Verify success message appears for removal");
        await expect(this.page.getByText("Customisation removed successfully")).toBeVisible();

        this.tester.isGoingTo("Verify student's customisation column is now empty (no longer shows Excluded)");
        const studentRow = this.page.locator("tr").filter({ hasText: studentName });
        await expect(studentRow.locator("td").nth(3)).toBeEmpty();
    }

    /**
     * Verify excluded class is present in class markbook
     * @param userId - The user ID of the student
     * @param activityId - The activity ID
     */
    async verifyClassMarkbookExcluded(userId: string, activityId: string): Promise<void> {
        this.tester.isGoingTo("Navigate to class markbook and verify excluded class is present");
        await this.page.goto(StudentParticipation.URL_CLASS_MARKBOOK);
        await expect(this.page.locator(`[data-test="markbook-cell-${activityId}-${userId}"]`)).toHaveClass(/excluded/);
    }

    /**
     * Verify excluded class is not present in class markbook
     * @param userId - The user ID of the student
     * @param activityId - The activity ID
     */
    async verifyClassMarkbookNotExcluded(userId: string, activityId: string): Promise<void> {
        this.tester.isGoingTo("Navigate to class markbook and verify cells no longer have excluded class");
        await this.page.goto(StudentParticipation.URL_CLASS_MARKBOOK);
        await expect(this.page.locator(`[data-test="markbook-cell-${activityId}-${userId}"]`)).not.toHaveClass(
            /excluded/,
        );
    }

    /**
     * Verify excluded class is present in course markbook
     * @param userId - The user ID of the student
     * @param activityId - The activity ID
     */
    async verifyCourseMarkbookExcluded(userId: string, activityId: string): Promise<void> {
        this.tester.isGoingTo("Navigate to course markbook and verify excluded class is present");
        await this.page.goto(StudentParticipation.URL_COURSE_MARKBOOK);
        await expect(this.page.locator(`[data-test="markbook-cell-${activityId}-${userId}"]`)).toHaveClass(/excluded/);
    }

    /**
     * Verify excluded class is not present in course markbook
     * @param userId - The user ID of the student
     * @param activityId - The activity ID
     */
    async verifyCourseMarkbookNotExcluded(userId: string, activityId: string): Promise<void> {
        this.tester.isGoingTo("Navigate to course markbook and verify cells no longer have excluded class");
        await this.page.goto(StudentParticipation.URL_COURSE_MARKBOOK);
        await expect(this.page.locator(`[data-test="markbook-cell-${activityId}-${userId}"]`)).not.toHaveClass(
            /excluded/,
        );
    }

    /**
     * Navigate back to the participation page
     */
    async returnToParticipationPage(): Promise<void> {
        this.tester.isGoingTo("Navigate back to participation page to remove the exclusion");
        await this.page.goto(StudentParticipation.URL_PARTICIPATION);
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
        dueDate: string,
    ): Promise<void> {
        this.tester.isGoingTo(`Click the Show Actions button for participant ${userId} (${studentName})`);
        await this.page.locator(`[data-test="sbx-pop-out-participant-${userId}-button"]`).click();

        this.tester.isGoingTo("Click the Modify button in the popup");
        await this.page.locator(`[id="participant-${userId}"]`).getByRole("button", { name: "Modify" }).click();

        this.tester.isGoingTo(`Add '${newTitle}' as the Title`);
        await this.page.getByLabel("Title").fill(newTitle);

        this.tester.isGoingTo(`Add '${description}' into the Description`);
        await this.page.getByLabel("Description").fill(description);

        this.tester.isGoingTo(`Add '${dueDate}' as the Due Date`);
        await this.page.getByLabel("Due Date").fill(dueDate);

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
        const calendarElement = this.page.locator("li.icon-calendar span");
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
        await expect(dueDateSpan).toBeDefined();
    }
}
