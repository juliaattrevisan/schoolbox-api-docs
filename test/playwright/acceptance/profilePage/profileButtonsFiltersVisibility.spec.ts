import { test } from "@schoolboxTest/playwright/support/integrations";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";

test.describe("Profile Page - Buttons and Filters visibility", () => {
    let profilePage: ProfilePage;
    const studentJanaId = 25; // Assuming 25 is the user ID for student Jana Reed

    test.beforeEach(async ({ tester, page }) => {
        profilePage = new ProfilePage(tester, page);
        await tester.loginAsAdmin();
        await profilePage.goto(studentJanaId);
    });

    test("Verify visibility of profile learning actions and filters", async ({ tester }) => {
        tester.isGoingTo(
            "Verify visibility of profile learning actions: Share learning moment, set goals and award achievements",
        );
        await profilePage.checkProfileActionButtonsVisible();
        await profilePage.checkFiltersAreVisible();
    });
});
