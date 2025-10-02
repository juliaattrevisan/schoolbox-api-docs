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
}
