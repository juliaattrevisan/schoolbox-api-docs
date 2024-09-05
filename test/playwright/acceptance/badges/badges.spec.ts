/**
 * This test adds, updates and deletes a badge
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test badges.spec.ts
 *  */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Badges from "@schoolboxTest/playwright/support/page/badges/Badges";
import CreateBadge from "@schoolboxTest/playwright/support/page/badges/CreateBadge";

test.describe("Badges tests", () => {
    /**
     * Deleting Bagades when the test is about to start so it does not get impacted by other tests already executed
     */
    test.beforeAll(async ({ tester, db }) => {
        tester.isGoingTo("update delete_at for the news article so it does not impact the news tests");
        await db.updateInDatabase("badge", { deleted_at: "2022-01-15 11:22:34" }, { "name like": "Automation Badge%" });
    });

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    /**
     * on learning/badge page click Create Badge button
     * Type a Name
     * Select a badge image via modal
     * Select a category
     * Click Create Badge
     * Ensure the Badge is presented in the /learning/badge screen
     */
    test("Create badge", async ({ tester, page }) => {
        const badges = new Badges(tester, page);
        const createBadge = new CreateBadge(tester, page);

        const badgeName = "Automation Badge";
        const category = "Learning";
        tester.isGoingTo("learning badges page");
        await badges.goto();

        tester.isGoingTo("click Create Badge button and create a badge");
        await badges.clickAddBadgeButton();
        await createBadge.typeName(badgeName);
        await createBadge.clickBrowseIconLibraryButton();
        await createBadge.waitModalDialogPresented();
        await createBadge.clickVerticalTabItem("food");
        await createBadge.clickBadgeImage("food/1");
        await createBadge.clickCategoryDropDown();
        await createBadge.clickCategoryItem(category);
        await createBadge.clickCreateBadgeButton();

        tester.isGoingTo("ensure the badge is presented");
        await badges.waitAddBadgeButtonToBePresented();
        await expect(page.locator("#container")).toContainText(badgeName);
        await expect(page.locator("#container")).toContainText(category);
    });

    /**
     * on /learning/badge select Modify badge
     * update the title
     * update the category
     * Click Modify Badge
     * Ensure the Badge title and category are properly presented in the /learning/badge screen
     */
    test("Modify badge", async ({ tester, page, db }) => {
        const badges = new Badges(tester, page);
        const createBadge = new CreateBadge(tester, page);

        const category = "Learning";
        tester.isGoingTo("click the badge dropdown and click Modify");
        await badges.goto();
        const badgeId = (await db.grabFromDatabase("badge", "id", {
            deleted_at: null,
            "name like": "Automation Badge",
        })) as number;
        await badges.clickBadgeDropdown(badgeId);
        await badges.clickDropdownModify(badgeId);

        tester.isGoingTo("get name and category updated and click Modify Badge button");
        await createBadge.typeName("Automation Badge Updated");
        await createBadge.clickCategoryDropDown();
        await createBadge.clickCategoryItem(category);
        await createBadge.clickCreateBadgeButton();
        await badges.waitAddBadgeButtonToBePresented();
        await expect(page.locator("#container")).toContainText("Automation Badge Updated");
        await expect(page.locator("#container")).toContainText(category);
    });

    /**
     * on /learning/badge click the Badge dropdown
     * Click Delete and confirm it
     * Ensure the Badge is no longer presented in the /learning/badge screen
     */
    test("Delete badge", async ({ tester, page, db }) => {
        const badges = new Badges(tester, page);

        tester.isGoingTo("click the badge dropdown and click Delete");
        await badges.goto();
        const badgeId = (await db.grabFromDatabase("badge", "id", {
            deleted_at: null,
            "name like": "Automation Badge Updated",
        })) as number;
        await badges.clickBadgeDropdown(badgeId);
        await badges.clickDropdownDelete();
        await badges.clickConfirm();
        await badges.goto();

        tester.isGoingTo("ensure the badge is no longer presented in the page");
        await expect(page.locator("#badge-list-container")).not.toContainText("Automation Badge Updated");

        tester.isGoingTo("reload the page to ensue the badge is not presented");
        await badges.goto();
        await expect(page.locator("#badge-list-container")).not.toContainText("Automation Badge Updated");
    });

    /**
     * Soft deleting data to not impact the tests
     */
    test.afterAll(async ({ tester, db }) => {
        tester.isGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
        await db.updateInDatabase("badge", { deleted_at: "2022-01-15 11:22:34" }, { "name like": "Automation Badge%" });
    });
});
