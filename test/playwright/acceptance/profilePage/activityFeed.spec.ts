import { test } from "@schoolboxTest/playwright/support/integrations";
import ActivityFeed from "@schoolboxTest/playwright/support/page/profilePage/ActivityFeed";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";

test.describe("Activity Feed", () => {
    const studentJanaId = 25; // Assuming 25 is the user ID for student Jana Reed

    test.beforeEach(async ({ tester, page }) => {
        await tester.loginAsAdmin();
        const profilePage = new ProfilePage(tester, page);
        await profilePage.goto(studentJanaId);
        await profilePage.clickTab("activity");
    });

    test("Verify visibility of activity feed actions", async ({ tester, page }) => {
        const activityFeed = new ActivityFeed(tester, page);
        await activityFeed.verifyShareLearningMomentButtonExists();
        await activityFeed.verifySetGoalsButtonExists();
        await activityFeed.verifyAwardAchievementsButtonExists();
    });

    test("Verify visibility of activity feed filters", async ({ tester, page }) => {
        const activityFeed = new ActivityFeed(tester, page);
        await activityFeed.checkFiltersAreVisible();
    });
});
