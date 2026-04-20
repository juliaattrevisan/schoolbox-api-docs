/**
 * Tests for the customisation records page filters.
 * 1. Provision sales.tar.gz data
 * 2. Execute DOCKER=1 npx playwright test customisationRecords.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CustomisationRecords from "@schoolboxTest/playwright/support/page/customisationRecords/CustomisationRecords";
import StudentParticipation from "@schoolboxTest/playwright/support/page/studentParticipation/StudentParticipation";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";

const classActivityId = "665";
const studentJanaId = "25";
const studentJanaName = "Reed, Jana";
const unitTitle = "Persuasion and the Media";

/**
 * Remove the created custom activity records from the database.
 * @param db - Database helper instance
 * @param customActivityTitle - Title of the custom activity to remove
 * @returns Promise that resolves when cleanup is complete
 */
async function cleanupCustomActivity(db: DatabaseHelper, customActivityTitle: string): Promise<void> {
    const customActivityId = (await db.grabFromDatabase("assessment", "id", {
        title: customActivityTitle,
        deleted_at: null,
    })) as number | null;

    if (customActivityId === null) {
        return;
    }

    const customPlanId = (await db.grabFromDatabase("participant_plan", "id", {
        assessment_id: customActivityId,
    })) as number | null;

    if (customPlanId !== null) {
        await db.dontHaveInDatabase("participant", { participant_plan_id: customPlanId });
    }

    const deletedAt = new Date().toISOString().slice(0, 19).replace("T", " ");
    await db.updateInDatabase("assessment", { deleted_at: deletedAt }, { id: customActivityId });
}

test.describe("Customisation Records", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("check customisation records", async ({ tester, page }) => {
        const customisationRecords: CustomisationRecords = new CustomisationRecords(tester, page);

        tester.isGoingTo("clear the existing filters and apply them on the customisation records page");
        await customisationRecords.goto();
        await customisationRecords.removeAllAppliedFilters();
        await customisationRecords.applyFilters();

        tester.isGoingTo("verify the filters display the proper number of records in the grid");
        await customisationRecords.expectGridVisible();
        await customisationRecords.expectGridRecordCount(2);

        tester.isGoingTo("verify the pagination summary at the bottom shows the proper number of records");
        await customisationRecords.expectPaginationSummary(1, 2, 2);

        tester.isGoingTo("verify the first record shows the user picture, name, year, and campus");
        await customisationRecords.expectFirstUserDetailsVisible(["Ali,", "Year 9", "Secondary Student"]);

        tester.isGoingTo("verify sorting by name shows the data accordingly");
        await customisationRecords.sortColumn("Name");
        await customisationRecords.expectFirstRowColumnToContain("user", "Reed,");

        tester.isGoingTo("verify sorting by created date shows the data accordingly");
        await customisationRecords.sortColumn("Created Date", 2);
        await customisationRecords.expectFirstRowColumnToContain("created_at", "May 9, 2018");

        tester.isGoingTo("verify sorting by class shows the data accordingly");
        await customisationRecords.sortColumn("Class");
        await customisationRecords.expectFirstRowColumnToContain("class", "9A English (9ENGA)");

        tester.isGoingTo("verify sorting by unit shows the data accordingly");
        await customisationRecords.sortColumn("Unit");
        await customisationRecords.expectFirstRowColumnToContain("unit", "To Kill a Mockingbird");

        tester.isGoingTo("verify sorting by customisation type shows the data accordingly");
        await customisationRecords.sortColumn("Customisation Type", 2);
        await customisationRecords.expectFirstRowColumnToContain("type", "Modified Activity");
    });

    test("exporting to csv downloads a file", async ({ tester, page }) => {
        const customisationRecords: CustomisationRecords = new CustomisationRecords(tester, page);

        try {
            tester.isGoingTo("open the customisation records page and apply filters so export is available");
            await customisationRecords.goto();
            await customisationRecords.removeAllAppliedFilters();
            await customisationRecords.applyFilters();
            await customisationRecords.expectGridVisible();

            tester.isGoingTo("export the customisation records to csv and confirm the file downloads");
            await customisationRecords.expectCsvDownload("report-customisation_records.csv.csv");
        } finally {
            // No cleanup required for CSV export verification.
        }
    });

    test("clicking the original activity redirects to the activity view page", async ({ tester, page }) => {
        const customisationRecords: CustomisationRecords = new CustomisationRecords(tester, page);

        try {
            tester.isGoingTo(
                "open the customisation records page and apply filters so the original activity links are shown",
            );
            await customisationRecords.goto();
            await customisationRecords.removeAllAppliedFilters();
            await customisationRecords.applyFilters();
            await customisationRecords.expectGridVisible();

            tester.isGoingTo("click the original activity link and verify it redirects to the activity view page");
            await customisationRecords.clickFirstRowColumnLink("original_activity");
            await expect(page).toHaveURL(/\/learning\/assessments\/\d+(?:\/\d+)?(?:\?.*)?$/);
        } finally {
            // No cleanup required for navigation verification.
        }
    });

    test("clicking the customised activity (modify) redirects to the participation plan page", async ({
        tester,
        page,
    }) => {
        const customisationRecords: CustomisationRecords = new CustomisationRecords(tester, page);

        try {
            tester.isGoingTo(
                "open the customisation records page and apply filters so the modified customised activity links are shown",
            );
            await customisationRecords.goto();
            await customisationRecords.removeAllAppliedFilters();
            await customisationRecords.applyFilters();
            await customisationRecords.expectGridVisible();

            tester.isGoingTo(
                "click the customised activity link for a modified activity and verify it redirects to the participation plan page",
            );
            await customisationRecords.clickRowColumnLink("Modified Activity", "customised_activity");
            await expect(page).toHaveURL(/\/learning\/assessments\/\d+\/participation(?:\?.*)?$/);
        } finally {
            // No cleanup required for navigation verification.
        }
    });

    test("create customisation and check it is properly presented in the grid", async ({ tester, page, db }) => {
        const customisationRecords: CustomisationRecords = new CustomisationRecords(tester, page);
        const studentParticipation = new StudentParticipation(tester, page);
        const assessmentForm = new AssessmentForm(tester, page);
        const customActivityTitle = `Customisation Records Grid Task ${Date.now()}`;
        const janaRow = page.locator("tr").filter({ hasText: studentJanaName });
        let removedExistingCustomisation = false;

        try {
            tester.isGoingTo("open student participation and remove any existing customisation for Jana Reed");
            await studentParticipation.goto(classActivityId);
            await studentParticipation.clickStudentDotMenu(studentJanaId);
            const removeCustomisationButton = page
                .locator(`[id="participant-${studentJanaId}"]`)
                .getByRole("button", { name: /Remove Customisation/ });

            if ((await removeCustomisationButton.count()) > 0) {
                const existingCustomisationText = ((await janaRow.textContent()) ?? "").replace(/\s+/g, " ").trim();

                tester.isGoingTo("remove Jana Reed's existing customisation");
                await removeCustomisationButton.click();
                await page.getByText("Customisation removed successfully").waitFor();
                removedExistingCustomisation = true;

                if (existingCustomisationText !== "") {
                    await expect(janaRow).not.toContainText(existingCustomisationText);
                }
            }

            tester.isGoingTo("create a new custom activity for Jana Reed");

            if (removedExistingCustomisation) {
                await studentParticipation.clickStudentDotMenu(studentJanaId);
            }

            await studentParticipation.clickStudentActionButton("Create New Activity", studentJanaId);
            await studentParticipation.chooseActivityType("task");
            await assessmentForm.setTitle(customActivityTitle);
            await assessmentForm.setWorkType(5);
            await assessmentForm.clickCreate();

            tester.isGoingTo(
                "verify the new custom activity appears in Jana's participation row before leaving the page",
            );
            await studentParticipation.checkCustomActivityInTable(studentJanaName, customActivityTitle);

            tester.isGoingTo("open the customisation records page and clear the default filters");
            await customisationRecords.goto();
            await customisationRecords.removeAllAppliedFilters();
            await customisationRecords.applyFilters();

            tester.isGoingTo("sort by created date and check the new customisation is properly presented in the grid");
            await customisationRecords.sortColumn("Created Date", 2);
            await customisationRecords.expectGridRowVisibleWithValues([
                customActivityTitle,
                "Reed,",
                unitTitle,
                "New Activity",
            ]);

            tester.isGoingTo(
                "click the customised activity link for the new activity and verify it redirects to the activity view page",
            );
            await Promise.all([
                page.waitForURL(/\/learning\/assessments\/\d+(?:\/\d+)?(?:\?.*)?$/),
                customisationRecords.clickRowColumnLink(customActivityTitle, "customised_activity"),
            ]);
        } finally {
            await cleanupCustomActivity(db, customActivityTitle);
        }
    });
});
