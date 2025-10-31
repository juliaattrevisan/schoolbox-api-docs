import { test } from "@schoolboxTest/playwright/support/integrations";
import MarkbookPage from "@schoolboxTest/playwright/support/page/markbook/MarkbookPage";
import TaskStatusPage from "@schoolboxTest/playwright/support/page/task/TaskStatusPage";

/**
 * mark a task via class markbook - check it reflects in the course markbook
 * mark a task via course markbook - check it reflects in the class markbook
 * uncheck a task via task status page - check it reflects in the markbook pages
 */

test.describe("Marking tasks sync between markbooks and task status page", () => {
    test.beforeEach(async ({ tester }) => {
        const teacher = "Jack Coles";
        await tester.loginAsAdmin();
        await tester.impersonate(teacher);
    });

    test("Tasks marks reflects across the class, course markbook and task status page", async ({
        tester,
        page,
        db,
    }) => {
        try {
            const markbookPage = new MarkbookPage(page);
            const taskStatusPage = new TaskStatusPage(page);
            // mario izzo id
            const studentId = 63;
            // assessment id for "Post to Forum: Prologue" - class page
            const assessmentId = 653;
            // assessment id for "Post to Forum: Prologue" - course page
            const courseAssessmentId = 59;
            // 9a english class id
            const classId = 83;
            // course id for year 9 english
            const courseId = 6;

            tester.isGoingTo("navigate to the class markbook and mark a task as completed");
            await markbookPage.gotoClassMarkbook(classId.toString());
            await markbookPage.markTask(assessmentId, studentId);

            await markbookPage.checkTaskStatus(assessmentId, studentId, "Completed");

            tester.isGoingTo("navigate to the course markbook and see the task is marked as completed");
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await markbookPage.checkTaskStatus(courseAssessmentId, studentId, "Completed");

            tester.isGoingTo("navigate to the task status page and see the task is marked as completed");
            await taskStatusPage.goto(assessmentId);
            await taskStatusPage.checkTaskMarkStatus(assessmentId, studentId, "Completed");
            await taskStatusPage.checkStudentMetaStatus("Mario Izzo", "Teacher-Assessed");

            tester.isGoingTo("unmark the task as completed via the task status page");
            await taskStatusPage.clickTaskMarkCell("Post to Forum: Prologue", "Mario Izzo");
            await taskStatusPage.checkTaskMarkStatus(assessmentId, studentId, "Incomplete");

            tester.isGoingTo("navigate to the class markbook and see the task is marked as incomplete");
            await markbookPage.gotoClassMarkbook(classId.toString());
            await markbookPage.checkTaskStatus(assessmentId, studentId, "Incomplete");

            tester.isGoingTo("navigate to the course markbook and see the task is marked as incomplete");
            await markbookPage.gotoCourseMarkbook(courseId.toString());
            await markbookPage.checkTaskStatus(courseAssessmentId, studentId, "Incomplete");

            tester.isGoingTo("mark the task as completed via the course markbook and check status is completed");
            await markbookPage.markTask(courseAssessmentId, studentId);
            await markbookPage.checkTaskStatus(courseAssessmentId, studentId, "Completed");

            tester.isGoingTo("navigate to the class markbook and see the task is marked as completed");
            await markbookPage.gotoClassMarkbook(classId.toString());
            await markbookPage.checkTaskStatus(assessmentId, studentId, "Completed");

            tester.isGoingTo("navigate to the task status page and see the task is marked as completed");
            await taskStatusPage.goto(assessmentId);
            await taskStatusPage.checkTaskMarkStatus(assessmentId, studentId, "Completed");
        } finally {
            tester.isGoingTo("clean up - unmark the task as completed via the task status page");
            await db.updateInDatabase("task_completion", { completion_status: 1 }, { task_id: 452, student_id: 63 });
        }
    });
});
