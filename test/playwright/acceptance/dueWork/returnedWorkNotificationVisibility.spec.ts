/**
 * Creates due work with four work type audience combinations, publishes feedback for Jana Reed,
 * and checks returned-work notifications are only visible to the matching student or parent audience.
 *
 * 1. Provision sales.tar.gz data.
 * Execute DOCKER=1 npx playwright test test/playwright/acceptance/dueWork/returnedWorkNotificationVisibility.spec.ts
 */
import type { Locator, Page } from "@playwright/test";
import { expect, test } from "@schoolboxTest/playwright/support/integrations";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import StatusPage from "@schoolboxTest/playwright/support/page/dueWork/StatusPage";
import Common from "@schoolboxTest/playwright/support/page/Common";

type WorkTypeConfig = {
    name: string;
    visibleToLearners: boolean;
    visibleToParents: boolean;
};

type CreatedDueWork = WorkTypeConfig & {
    assessmentId: number;
    title: string;
};

const CLEANUP_DELETED_AT = "2022-01-15 11:22:34";

/**
 * Get a current timestamp formatted for MariaDB datetime fields.
 * @returns The current timestamp in a database-compatible format.
 */
function nowForDatabase(): string {
    return new Date().toISOString().slice(0, 19).replace("T", " ");
}

/**
 * Convert a boolean to the tinyint values used by legacy lookup tables.
 * @param value The value to convert.
 * @returns 1 when true, otherwise 0.
 */
function flag(value: boolean): number {
    return value ? 1 : 0;
}

/**
 * Create a work type with the requested learner and parent audience visibility.
 * @param db Database helper.
 * @param workType Work type visibility configuration.
 * @param sequence Display sequence for the work type.
 * @returns The created work type id.
 */
async function createWorkType(db: DatabaseHelper, workType: WorkTypeConfig, sequence: number): Promise<number> {
    const now = nowForDatabase();

    return db.haveInDatabase("lu_work_type", {
        name: workType.name,
        external_result_type: null,
        show_on_grades_page: 1,
        _staff_or_instructor_visible_only: flag(!workType.visibleToLearners && !workType.visibleToParents),
        is_visible_to_parents: flag(workType.visibleToParents),
        is_visible_to_learners: flag(workType.visibleToLearners),
        permitted_project: 0,
        permitted_due_work: 1,
        permitted_task: 0,
        permitted_quiz: 0,
        permitted_lti: 0,
        permitted_lesson_plan: 0,
        permitted_dialog_agent: 0,
        permitted_reporting_activity: 0,
        sequence,
        updated_by: 1,
        created_at: now,
        updated_at: now,
        deleted_at: null,
    });
}

/**
 * Create a due work assessment for the given work type and return its assessment id.
 * @param tester Acceptance tester.
 * @param page Playwright page.
 * @param db Database helper.
 * @param workTypeId Work type id to assign to the due work.
 * @param title Assessment title.
 * @returns The created assessment id.
 */
async function createDueWork(
    tester: AcceptanceTester,
    page: Page,
    db: DatabaseHelper,
    workTypeId: number,
    title: string,
): Promise<number> {
    const dueWorkForm = new AssessmentForm(tester, page);

    await dueWorkForm.goto(Common.CLASS_ID_9AENG.toString());
    await dueWorkForm.setWorkType(workTypeId);
    await dueWorkForm.setAcceptSubmission(AssessmentForm.ACCEPT_SUBMISSION_NONE);
    await dueWorkForm.setTitle(title);
    await dueWorkForm.setInstructorMarkType(AssessmentForm.MARK_TYPE_PERCENTAGE);
    await dueWorkForm.setVisibility(AssessmentForm.VISIBILITY_ALL.toString());
    await dueWorkForm.create();

    const assessmentId = await db.grabFromDatabase("assessment", "id", { title, deleted_at: null });
    expect(assessmentId).not.toBeNull();

    return Number(assessmentId);
}

/**
 * Publish a unique percentage mark and feedback comment for Jana Reed on each assessment.
 * @param tester Acceptance tester.
 * @param page Playwright page.
 * @param dueWorks Due work assessments to mark.
 */
async function publishFeedbackForJana(tester: AcceptanceTester, page: Page, dueWorks: CreatedDueWork[]): Promise<void> {
    const statusPage = new StatusPage(tester, page);

    for (let i = 0; i < dueWorks.length; i += 1) {
        const dueWork = dueWorks[i];
        await statusPage.goto(dueWork.assessmentId);
        await statusPage.gradeSubmission(
            Common.USER_ID_JANAREED,
            (i + 1) * 10,
            `${dueWork.title} feedback for Jana Reed`,
        );
    }
}

/**
 * Find a returned-work notification by title for the current user.
 * @param page Playwright page.
 * @param title Due work title.
 * @param failIfNotFound Whether the assertion should fail when the notification is absent.
 * @param retries Number of refresh retries while waiting for async notification delivery.
 * @returns The matching notification locator.
 */
async function findReturnedWorkNotification(
    page: Page,
    title: string,
    failIfNotFound: boolean,
    retries: number = 60,
): Promise<Locator> {
    await page.goto("/notifications");

    const notificationWithTitle = page
        .getByTestId("notification-list-notification-body")
        .filter({ hasText: title })
        .filter({ hasText: "has marked" });

    if (retries > 0 && (await notificationWithTitle.count()) === 0) {
        await page.waitForTimeout(1000);
        await page.reload();
        return findReturnedWorkNotification(page, title, failIfNotFound, retries - 1);
    }

    if (failIfNotFound) {
        await expect(notificationWithTitle).toBeVisible();
    }

    return notificationWithTitle;
}

/**
 * Check visible and hidden due work titles against the current user's returned-work notifications.
 * @param page Playwright page.
 * @param visibleDueWorks Due work assessments expected in the notification list.
 * @param hiddenDueWorks Due work assessments not expected in the notification list.
 */
async function checkNotificationVisibility(
    page: Page,
    visibleDueWorks: CreatedDueWork[],
    hiddenDueWorks: CreatedDueWork[],
): Promise<void> {
    for (const dueWork of visibleDueWorks) {
        await findReturnedWorkNotification(page, dueWork.title, true);
    }

    for (const dueWork of hiddenDueWorks) {
        const notification = await findReturnedWorkNotification(page, dueWork.title, false, 1);
        await expect(notification).toHaveCount(0);
    }
}

test.describe("Returned work notification visibility", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("returned work notifications respect work type student and parent visibility", async ({
        tester,
        page,
        db,
    }) => {
        const runId = Math.random().toString(36).slice(2, 8);
        const workTypePrefix = `SA21973 ${runId}`;
        const workTypeConfigs: WorkTypeConfig[] = [
            {
                name: `${workTypePrefix} staff only`,
                visibleToLearners: false,
                visibleToParents: false,
            },
            {
                name: `${workTypePrefix} staff students`,
                visibleToLearners: true,
                visibleToParents: false,
            },
            {
                name: `${workTypePrefix} staff parents`,
                visibleToLearners: false,
                visibleToParents: true,
            },
            {
                name: `${workTypePrefix} staff students parents`,
                visibleToLearners: true,
                visibleToParents: true,
            },
        ];
        const dueWorks: CreatedDueWork[] = [];

        try {
            tester.isGoingTo("create the four work types used by the notification visibility matrix");
            const workTypeIds = await Promise.all(
                workTypeConfigs.map((workType, index) => createWorkType(db, workType, 9000 + index)),
            );

            tester.isGoingTo("as Jack Coles, create four due work assessments with percentage marks");
            await tester.impersonateViaUserProfilePage(Common.USER_JACKCOLES);
            for (let i = 0; i < workTypeConfigs.length; i += 1) {
                const workType = workTypeConfigs[i];
                const title = `${workType.name} test`;
                const assessmentId = await createDueWork(tester, page, db, workTypeIds[i], title);

                dueWorks.push({
                    ...workType,
                    assessmentId,
                    title,
                });
            }

            tester.isGoingTo("as Jack Coles, publish different feedback for Jana Reed on each assessment");
            await publishFeedbackForJana(tester, page, dueWorks);

            tester.isGoingTo("as Jana Reed, check only learner-visible returned-work notifications are visible");
            await tester.impersonateViaUserProfilePage(Common.USER_JANAREED);
            await checkNotificationVisibility(
                page,
                dueWorks.filter((dueWork) => dueWork.visibleToLearners),
                dueWorks.filter((dueWork) => !dueWork.visibleToLearners),
            );

            tester.isGoingTo("as Jenni Reed, check only parent-visible returned-work notifications are visible");
            await tester.impersonateViaUserProfilePage(Common.USER_JENNIREED);
            await checkNotificationVisibility(
                page,
                dueWorks.filter((dueWork) => dueWork.visibleToParents),
                dueWorks.filter((dueWork) => !dueWork.visibleToParents),
            );
        } finally {
            tester.isGoingTo("clean up returned work notification visibility test data");
            await db.updateInDatabase(
                "assessment",
                { deleted_at: CLEANUP_DELETED_AT },
                { "title LIKE": `${workTypePrefix}%` },
            );
            await db.updateInDatabase(
                "lu_work_type",
                { deleted_at: CLEANUP_DELETED_AT },
                { "name LIKE": `${workTypePrefix}%` },
            );
        }
    });
});
