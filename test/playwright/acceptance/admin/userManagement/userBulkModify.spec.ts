/**
 * Tests user bulk modify filtering and pagination.
 *
 * Prerequisites:
 * 1. Provision sales.tar.gz data
 *
 * Execute: DOCKER=1 npx playwright test userBulkModify.spec.ts
 */

import { test } from "@schoolboxTest/playwright/support/integrations";
import UserBulkModify from "@schoolboxTest/playwright/support/page/admin/userManagement/UserBulkModify";
import Common from "@schoolboxTest/playwright/support/page/Common";

test.describe("User bulk modify: filters, sort, pagination and delete", () => {
    let userBulkModify: UserBulkModify;
    let common: Common;

    test.beforeEach(async ({ tester, page }) => {
        userBulkModify = new UserBulkModify(tester, page);
        common = new Common(page);

        tester.isGoingTo("Login as admin");
        await tester.loginAsAdmin();
        await userBulkModify.goTo();
    });

    test("Filtering by searching users, using the filters and checking pagination", async () => {
        await userBulkModify.searchUsers("ree");
        await userBulkModify.expectVisibleRows(5);
        await userBulkModify.expectPaginationText("1-5 of 5");

        await userBulkModify.clearSearch();
        await userBulkModify.searchUsers("jana");
        await userBulkModify.expectVisibleRows(1);

        const filters = [
            { method: userBulkModify.filterByCampus.bind(userBulkModify), value: "Primary", expectedRows: 5 },
            { method: userBulkModify.filterByRole.bind(userBulkModify), value: "Middle Student", expectedRows: 12 },
            { method: userBulkModify.filterByYearLevel.bind(userBulkModify), value: "4", expectedRows: 1 },
        ];

        for (const filter of filters) {
            await userBulkModify.resetFilters();
            await filter.method(filter.value);
            await userBulkModify.expectVisibleRows(filter.expectedRows);
        }

        await userBulkModify.resetFilters();
        await userBulkModify.filterByToken("1");
        await userBulkModify.expectNoMatchingUsers();

        await userBulkModify.resetFilters();
        await userBulkModify.filterByEportfolio("1");
        await userBulkModify.expectVisibleRows(25);
        await userBulkModify.expectPaginationText("1-25 of 25");

        await userBulkModify.filterByCampus("Secondary");
        await userBulkModify.filterByRole("Middle Student");
        await userBulkModify.expectVisibleRows(3);
    });

    // commented part to be resolved when SA-22472 is sorted.
    test("Sorting columns", async () => {
        await userBulkModify.sortColumnAscending("First Name");
        await userBulkModify.expectTopRowColumnText(UserBulkModify.COLUMN_FIRST_NAME, "Adam");

        // await userBulkModify.sortColumnDescending("Surname");
        // await userBulkModify.expectTopRowColumnText(UserBulkModify.COLUMN_FIRST_NAME, "Rebecca");

        await userBulkModify.sortColumnAscending("External ID");
        await userBulkModify.expectTopRowColumnText(UserBulkModify.COLUMN_FIRST_NAME, "Jack");

        // await userBulkModify.sortColumnDescending("Campuses");
        // await userBulkModify.expectTopRowColumnText(UserBulkModify.COLUMN_FIRST_NAME, "Rebecca");

        await userBulkModify.sortColumnAscending("Role");
        await userBulkModify.expectTopRowColumnText(UserBulkModify.COLUMN_FIRST_NAME, "Michael");

        // await userBulkModify.sortColumnDescending("Year Level");
        // await userBulkModify.expectTopRowColumnText(UserBulkModify.COLUMN_FIRST_NAME, "Adam");

        await userBulkModify.sortColumnDescending("ePortfolio");
        await userBulkModify.expectTopRowColumnText(UserBulkModify.COLUMN_FIRST_NAME, "Jack");
    });

    test("Delete users", async ({ db }) => {
        try {
            await userBulkModify.searchUsers("staff 20");
            await userBulkModify.expectVisibleRows(1);
            await userBulkModify.selectFirstUser();
            await userBulkModify.clickDeleteButton();
            await common.clickModalDeleteButton();
            await common.closeModal();

            await userBulkModify.searchUsers("staff 20");
            await userBulkModify.expectNoMatchingUsers();
        } finally {
            await db.updateInDatabase("user", { deleted_at: null }, { id: 84 });
        }
    });
});
