import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";

export default class TaskStatusPage {
    private page: Page;

    static readonly URL = "/learning/assessments/{assessmentId}/status";

    static readonly SELECTOR_TASK_MARK_CELL = `form[action="/learning/assessments/{assessmentId}/{studentId}/submit"]`;

    static readonly DATA_TEST_TASK_TITLE = "tick-{taskTitle}";

    static readonly SELECTOR_STUDENT_ROW = `li[data-participant-name="{studentName}"]`;

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to the task status page
     * @param assessmentId - The assessment id
     */
    async goto(assessmentId: number): Promise<void> {
        await this.page.goto(TaskStatusPage.URL.replace("{assessmentId}", assessmentId.toString()));
    }

    /**
     * Checks the task mark status for a student
     * @param assessmentId - The assessment id
     * @param studentId - The student id
     * @param expectedStatus - The expected status (e.g., "Completed", "Incomplete")
     */
    async checkTaskMarkStatus(assessmentId: number, studentId: number, expectedStatus: string): Promise<void> {
        const taskMarkCell: Locator = this.page.locator(
            TaskStatusPage.SELECTOR_TASK_MARK_CELL.replace("{assessmentId}", assessmentId.toString()).replace(
                "{studentId}",
                studentId.toString(),
            ),
        );
        await expect(taskMarkCell.locator("a")).toHaveAttribute("title", new RegExp(expectedStatus));
    }

    /**
     * Finds the student row and clicks the task mark cell for a student
     * @param taskTitle - The task title
     * @param studentName - The student name
     */
    async clickTaskMarkCell(taskTitle: string, studentName: string): Promise<void> {
        const student = this.page.locator(TaskStatusPage.SELECTOR_STUDENT_ROW.replace("{studentName}", studentName));
        const taskMarkTick: Locator = student.getByTestId(
            TaskStatusPage.DATA_TEST_TASK_TITLE.replace("{taskTitle}", taskTitle),
        );
        await taskMarkTick.click();
    }

    /**
     * Finds the student row and checks the meta status under the student name
     * @param studentName - The student name
     * @param expectedStatus - The expected status (e.g., "Completed", "Incomplete")
     */
    async checkStudentMetaStatus(studentName: string, expectedStatus: string): Promise<void> {
        const student = this.page.locator(TaskStatusPage.SELECTOR_STUDENT_ROW.replace("{studentName}", studentName));
        await expect(student).toContainText(expectedStatus);
    }
}
