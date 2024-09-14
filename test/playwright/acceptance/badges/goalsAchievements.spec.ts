import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Badges from "@schoolboxTest/playwright/support/page/badges/Badges";
import CreateBadge from "@schoolboxTest/playwright/support/page/badges/CreateBadge";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";

/**
 * This test sets a goal, award an achievement, remove the goal, withdraw the achievement, adds an evidence
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test goalsAchievements.spec.ts
 */

test.describe("Goals and Achievements tests", () => {
    /**
     * Deleting Bagdes when the test is about to start so it does not get impacted by other tests already executed
     */
    test.beforeAll(async ({ tester, db }) => {
        tester.isGoingTo("update delete_at for the badge so it does not impact the news tests");
        await db.updateInDatabase("badge", { deleted_at: "2022-01-15 11:22:34" }, { "name like": "Automation Badge%" });
    });

    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    /**
     * on learning/badge page create a Badge
     * set it as a goal to Jana Reed
     * ensure the goal is presented on Jana Reed's activity feed
     */
    test("Set goals", async ({ tester, page, db }) => {
        const badges = new Badges(tester, page);
        const createBadge = new CreateBadge(tester, page);
        const profilePage = new ProfilePage(tester, page);

        const badgeName = "Automation Badge created to set goal from Badges page";
        const student = "Jana Reed";
        const studentId = 25;
        await badges.goto();
        let badgeId = (await db.grabFromDatabase("badge", "id", {
            deleted_at: null,
            "name like": badgeName,
        })) as number;
        if (!badgeId) {
            tester.isGoingTo("Create a Badge");
            await createBadge.createBadge(tester, page, badgeName);
            badgeId = (await db.grabFromDatabase("badge", "id", {
                deleted_at: null,
                "name like": badgeName,
            })) as number;
        }
        tester.isGoingTo("Set a Goal to Jana Reed");
        tester.isGoingTo("click the 3-dot menu and click Set Goal");
        await badges.clickBadgeDropdown(badgeId);
        await badges.clickDropdownOptionFirstElement(badgeId, "goal");
        tester.isGoingTo("search and select Jana Reed on the modal");
        await badges.selectStudentSetGoal(student);
        tester.isGoingTo("click Set Goal button on the modal");

        await badges.clickSetGoalorAwardAchievement("Set goal");

        tester.isGoingTo("check the goal is presented on Jana Reed profile page activity feed");
        await profilePage.goto(studentId.toString());
        await expect(page.locator("#container")).toContainText(badgeName);
        tester.isGoingTo("check the goal is presented on Jana Reed profile page tab Goals");
        await profilePage.clickTab("goals");
        await expect(page.locator("#container")).toContainText(badgeName);
    });

    /*
     * award an achievement to Jana Reed from the Badges page
     * ensure the achievement is presented on Jana Reed's activity feed
     */
    test("Award achievement", async ({ tester, page, db }) => {
        const createBadge = new CreateBadge(tester, page);
        const badges = new Badges(tester, page);
        const profilePage = new ProfilePage(tester, page);
        const badgeName = "Automation Badge created for award achievement from Badges page";
        const student = "Jana Reed";
        const studentId = 25;
        await badges.goto();
        let badgeId = (await db.grabFromDatabase("badge", "id", {
            deleted_at: null,
            "name like": badgeName,
        })) as number;
        if (!badgeId) {
            tester.isGoingTo("Create a Badge");
            await createBadge.createBadge(tester, page, badgeName);
            badgeId = (await db.grabFromDatabase("badge", "id", {
                deleted_at: null,
                "name like": badgeName,
            })) as number;
        }
        tester.isGoingTo("Award achievement to Jana Reed");
        tester.isGoingTo("click the 3-dot menu and click Award Achievement");
        await badges.clickBadgeDropdown(badgeId);
        await badges.clickDropdownOptionFirstElement(badgeId, "achievement");
        tester.isGoingTo("search and select Jana Reed on the modal");
        await badges.selectStudentAwardAchievement(student);
        tester.isGoingTo("click Award achievement button on the modal");
        await badges.clickSetGoalorAwardAchievement("Award");
        tester.isGoingTo("check the achievement is presented on Jana Reed activity feed");
        await profilePage.goto(studentId.toString());
        await expect(page.locator("#container")).toContainText(badgeName);
    });

    /**
     * remove the goal and withdraw the achievement from Jana Reed
     * ensure the goals and achievements are  no longer presented on Jana Reed's activity feed, goals and achievement tabs on profile page
     */

    test("Remove goal and withdraw achievement", async ({ tester, page, db }) => {
        const badges = new Badges(tester, page);
        const profilePage = new ProfilePage(tester, page);
        const badgeNameGoal = "Automation Badge created to set goal from Badges page";
        const badgeNameAchievement = "Automation Badge created for award achievement from Badges page";
        const studentId = 25;
        tester.isGoingTo("Navigate to Jana Reed profile page, click Goals tab and award the goal as achievement");
        await profilePage.goto(studentId.toString());
        await profilePage.clickTab("goals");
        await expect(page.locator("#goal-feed")).toContainText(badgeNameGoal);
        await profilePage.clickBadgeOrDropdownOption(badgeNameGoal);
        await profilePage.clickButtonInModal("Award");

        tester.isGoingTo("Click the achievements tab, ensure the achievements are presented and withdraw them");
        await profilePage.clickTab("achievements");
        await expect(page.locator("#achievement-feed")).toContainText(badgeNameGoal);
        let badgeIdAchievement = (await db.grabFromDatabase("badge", "id", {
            deleted_at: null,
            "name like": badgeNameAchievement,
        })) as number;
        await profilePage.clickBadgeAchievementDropdown(badgeIdAchievement);
        await profilePage.clickBadgeOrDropdownOption("Withdraw achievement");
        await badges.clickConfirm();
        badgeIdAchievement = (await db.grabFromDatabase("badge", "id", {
            deleted_at: null,
            "name like": badgeNameGoal,
        })) as number;
        await profilePage.clickBadgeAchievementDropdown(badgeIdAchievement);
        await profilePage.clickBadgeOrDropdownOption("Withdraw achievement");
        await badges.clickConfirm();
        tester.isGoingTo("Click the goals tab, ensure the goal is presented and remove it");
        await profilePage.clickTab("goals");
        await expect(page.locator("#container")).toContainText(badgeNameGoal);
        await expect(page.locator("#container")).not.toContainText(badgeNameAchievement);

        const badgeIdGoal = (await db.grabFromDatabase("badge", "id", {
            deleted_at: null,
            "name like": badgeNameGoal,
        })) as number;
        await profilePage.clickBadgeDropdown(badgeIdGoal);
        await badges.clickDropdownOptionLastElement("close");
        await badges.clickConfirm();
        await expect(page.locator('[class="icon-goal"]')).toBeVisible();
        await profilePage.clickTab("activity");
        await expect(page.locator("#activity-feed")).not.toContainText(badgeNameAchievement);
        await expect(page.locator("#activity-feed")).not.toContainText(badgeNameGoal);
    });

    /**
     * set a goal, add an evidence, view the evidence, edit it, check it is updated, award it and check the evidence is presented
     * withdraw the achievement, remove the evidence, award it again with evidence, check the evidence, update the evidence, check the evidence is presented properly
     *
     */

    test("View evidence", async ({ tester, page, db }) => {
        const badges = new Badges(tester, page);
        const profilePage = new ProfilePage(tester, page);
        const badgeNameGoal = "Automation Badge created to set goal from Badges page";
        const student = "Jana Reed";
        const studentId = 25;
        tester.isGoingTo("Set a Goal to Jana Reed");
        await badges.goto();
        const badgeId = (await db.grabFromDatabase("badge", "id", {
            deleted_at: null,
            "name like": badgeNameGoal,
        })) as number;
        tester.isGoingTo("click the 3-dot menu and click Set Goal");
        await badges.clickBadgeDropdown(badgeId);
        await badges.clickDropdownOptionFirstElement(badgeId, "goal");
        tester.isGoingTo("search and select Jana Reed on the modal");
        await badges.selectStudentSetGoal(student);
        tester.isGoingTo("click Set Goal button on the modal");
        await badges.clickSetGoalorAwardAchievement("Set goal");

        tester.isGoingTo("Navigate to Jana Reed profile page goals tab and add an evidence to the goal");
        await profilePage.goto(studentId.toString());
        await profilePage.clickTab("goals");
        await expect(page.locator("#goal-feed")).toContainText(badgeNameGoal);
        await profilePage.clickBadgeOrDropdownOption(badgeNameGoal);
        await profilePage.clickBadgeOrDropdownOption("Add evidence");
        await tester.fillCKEditorById("reason", "Great job, Jana!");
        await badges.clickEvidenceSubmitButton();

        tester.isGoingTo("Check the evidence is presented by navigating to view evidence");
        await profilePage.goto(studentId.toString());
        await profilePage.clickTab("goals");
        await profilePage.clickBadgeOrDropdownOption(badgeNameGoal);
        await profilePage.clickBadgeOrDropdownOption("View evidence");
        await expect(page.locator("#container")).toContainText("Great job, Jana!");

        tester.isGoingTo("Get the evidence updated");
        await page.locator('[class="icon-modify"]').last().click();
        await tester.fillCKEditorById("reason", "Well done, Jana!");
        await badges.clickEvidenceSubmitButton();

        tester.isGoingTo("Ensure the evidence text is updated");
        await expect(page.locator("#container")).toContainText("Well done, Jana!");

        tester.isGoingTo(
            "Award the goal as achievement and check if it is presented on the achievements tab on the profile page",
        );
        await badges.clickConfirm();
        await expect(page.locator('[class="icon-achievement"]')).toBeVisible();
        await profilePage.goto(studentId.toString());
        await profilePage.clickTab("achievements");
        await expect(page.locator("#achievement-feed")).toContainText(badgeNameGoal);

        tester.isGoingTo("Check the evidence is still presented");
        await profilePage.clickBadgeOrDropdownOption(badgeNameGoal);
        await profilePage.clickBadgeOrDropdownOption("View evidence");
        await expect(page.locator("#container")).toContainText("Well done, Jana!");

        tester.isGoingTo("Navigate to the profile page and withdraw the achievement");
        await profilePage.goto(studentId.toString());
        await profilePage.clickTab("achievements");
        await profilePage.clickBadgeAchievementDropdown(badgeId);
        await profilePage.clickBadgeOrDropdownOption("Withdraw achievement");
        await badges.clickConfirm();

        tester.isGoingTo("click the tab Goals and ensure the evidence is still presented to the goal");
        await profilePage.clickTab("goals");
        await profilePage.clickBadgeOrDropdownOption(badgeNameGoal);
        await profilePage.clickBadgeOrDropdownOption("View evidence");
        await expect(page.locator("#container")).toContainText("Well done, Jana!");

        tester.isGoingTo("Remove the evidence and ensure it is no longer presented");
        await badges.clickDropdownDelete();
        await page.locator("a#confirm-dialog-confirm-button.button.submit").click();
        await expect(page.locator("#container")).not.toContainText("Well done, Jana!");

        tester.isGoingTo("Award the goal as achievement with evidence and check the evidence");
        await profilePage.goto(studentId.toString());
        await profilePage.clickTab("goals");
        await profilePage.clickBadgeOrDropdownOption(badgeNameGoal);
        await profilePage.clickBadgeOrDropdownOption("Award with evidence");
        await tester.fillCKEditorById("reason", "Awarding this as achievement with evidence.");
        await badges.clickEvidenceSubmitButton();
        await profilePage.goto(studentId.toString());
        await profilePage.clickTab("achievements");
        await profilePage.clickBadgeOrDropdownOption(badgeNameGoal);
        await profilePage.clickBadgeOrDropdownOption("View evidence");
        await expect(page.locator("#container")).toContainText("Awarding this as achievement with evidence");

        tester.isGoingTo("Get the evidence updated and ensure it is presented properly");
        await page.locator('[class="icon-modify"]').last().click();
        await tester.fillCKEditorById("reason", "This is updated");
        await badges.clickEvidenceSubmitButton();
        tester.isGoingTo("Ensure the evidence text is updated");
        await expect(page.locator("#container")).toContainText("This is updated");
    });

    /**
     * Soft deleting data to not impact the tests
     */
    test.afterAll(async ({ tester, db }) => {
        tester.isGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
        await db.updateInDatabase(
            "badge",
            { deleted_at: "2022-01-15 11:22:34" },
            {
                "name like": "Automation Badge%",
            },
        );
    });
});
