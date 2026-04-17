/**
 * Tests for the customisation records page filters.
 * 1. Provision sales.tar.gz data
 * 2. Execute DOCKER=1 npx playwright test customisationRecords.spec.ts
 */

import { test } from "@schoolboxTest/playwright/support/integrations";
import CustomisationRecords from "@schoolboxTest/playwright/support/page/customisationRecords/CustomisationRecords";

test.describe("Customisation Records", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("check customisation records", async ({ tester, page }) => {
        const customisationRecords = new CustomisationRecords(tester, page);

        tester.isGoingTo("clear the existing filters and apply them on the customisation records page");
        await customisationRecords.goto();
        await customisationRecords.removeAllAppliedFilters();
        await customisationRecords.applyFilters();

        tester.isGoingTo("verify the filters display the proper number of records in the grid");
        await customisationRecords.expectGridVisible();
        await customisationRecords.expectGridRecordCount(4);

        tester.isGoingTo("verify the first record shows the user picture, name, year, and campus");
        await customisationRecords.expectFirstUserDetailsVisible(["Ali,", "Year 9", "Secondary Student"]);

        tester.isGoingTo("verify sorting by name shows the data accordingly");
        await customisationRecords.sortColumn("Name");
        await customisationRecords.expectFirstRowColumnToContain("user", "Reed,");

        tester.isGoingTo("verify sorting by created date shows the data accordingly");
        await customisationRecords.sortColumn("Created Date", 2);
        await customisationRecords.expectFirstRowColumnToContain("created_at", "Apr 17, 2026 11:56am");

        tester.isGoingTo("verify sorting by class shows the data accordingly");
        await customisationRecords.sortColumn("Class");
        await customisationRecords.expectFirstRowColumnToContain("class", "9A English (9ENGA)");

        tester.isGoingTo("verify sorting by unit shows the data accordingly");
        await customisationRecords.sortColumn("Unit");
        await customisationRecords.expectFirstRowColumnToContain("unit", "Persuasion and the Media");

        tester.isGoingTo("verify sorting by customisation type shows the data accordingly");
        await customisationRecords.sortColumn("Customisation Type", 2);
        await customisationRecords.expectFirstRowColumnToContain("type", "New Activity");
    });
});
