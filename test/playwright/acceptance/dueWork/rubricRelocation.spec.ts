/**
 * Tests relocating Due Work while deleting one of its rubrics.
 * 1. Provision sales.tar.gz data
 * 2. Execute DOCKER=1 npx playwright test rubricRelocation.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import RubricBuilder from "@schoolboxTest/playwright/support/page/rubric/RubricBuilder";
import UnitPlanner from "@schoolboxTest/playwright/support/page/unitPlanner/UnitPlanner";

test.describe("Due Work rubric relocation", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("deletes a rubric while relocating Due Work", async ({ tester, page, db, api }) => {
        const courseId = 6; // Year 9 English
        const sourceUnitId = 15; // Romeo and Juliet
        const destinationUnitName = "To Kill a Mockingbird";
        const capabilityId = 86; // Grammar
        const indicatorId = 426; // Coherence
        const assessmentTitle = `SA-23089 rubric relocation ${Date.now()}`;

        const assessmentForm = new AssessmentForm(tester, page);
        const rubricBuilder = new RubricBuilder(page);
        const unitPlanner = new UnitPlanner(tester, page);

        try {
            tester.isGoingTo("create Due Work with two rubrics in the Romeo and Juliet unit");
            await unitPlanner.goto(courseId);
            await page
                .getByTestId(UnitPlanner.DATA_TEST_CREATE_ACTIVITY_BUTTON.replace("{unitId}", sourceUnitId.toString()))
                .click();
            await unitPlanner.clickCreateActivityOption("dueWork", sourceUnitId);
            await assessmentForm.setTitle(assessmentTitle);
            await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
            await rubricBuilder.createManualRubric(-1, "Retained rubric", capabilityId, indicatorId, [1, 2, 3]);
            await rubricBuilder.createManualRubric(-2, "Deleted rubric", capabilityId, indicatorId, [1, 2, 3]);
            await assessmentForm.clickCreate();

            let originalAssessmentId = 0;
            await expect
                .poll(async () => {
                    originalAssessmentId = Number(
                        await db.grabFromDatabase("assessment", "id", {
                            title: assessmentTitle,
                            deleted_at: null,
                        }),
                    );
                    return originalAssessmentId;
                })
                .toBeGreaterThan(0);

            let deletedRubricId = 0;
            await expect
                .poll(async () => {
                    deletedRubricId = Number(
                        await db.grabFromDatabase("rubric_instance", "id", {
                            assessment_id: originalAssessmentId,
                            title: "Deleted rubric",
                            deleted_at: null,
                        }),
                    );
                    return deletedRubricId;
                })
                .toBeGreaterThan(0);

            tester.isGoingTo("delete one rubric and relocate the Due Work in the same update");
            await assessmentForm.gotoAssessmentModifyPage(originalAssessmentId.toString());
            await rubricBuilder.deleteRubric(deletedRubricId);
            await page.locator("#change-location").click();
            await page.getByRole("treeitem", { name: new RegExp(destinationUnitName) }).click();
            await page.getByRole("button", { name: "Change Location" }).click();
            await assessmentForm.update();

            let relocatedAssessmentId = originalAssessmentId;
            await expect
                .poll(async () => {
                    relocatedAssessmentId = Number(
                        await db.grabFromDatabase("assessment", "id", {
                            title: assessmentTitle,
                            deleted_at: null,
                        }),
                    );
                    return relocatedAssessmentId;
                })
                .not.toBe(originalAssessmentId);
            await expect(page.getByRole("heading", { name: "Schoolbox has encountered a fatal error." })).toHaveCount(
                0,
            );
            expect(
                await db.countInDatabase("rubric_instance", {
                    assessment_id: relocatedAssessmentId,
                    deleted_at: null,
                }),
            ).toBe(1);
            expect(
                await db.countInDatabase("rubric_instance", {
                    assessment_id: relocatedAssessmentId,
                    title: "Retained rubric",
                    deleted_at: null,
                }),
            ).toBe(1);
        } finally {
            tester.isGoingTo("clean up the Due Work created by the rubric relocation test");
            const activeAssessmentIds = (await db.grabColumnFromDatabase("assessment", "id", {
                title: assessmentTitle,
                deleted_at: null,
            })) as number[];

            for (const assessmentId of activeAssessmentIds) {
                await api.deleteAssessment(assessmentId);
            }
        }
    });
});
