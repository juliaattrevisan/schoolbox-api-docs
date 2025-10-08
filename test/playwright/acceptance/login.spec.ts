/**
 * Login successfully, login with invalid password,
 * SAML tests do not exist in the Playwright implementation yet, see codeception
 */
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import LoginPage from "@schoolboxTest/playwright/support/page/LoginPage";

test.describe("Login functionality tests", () => {
    let loginPage: LoginPage;

    test.beforeEach(async ({ page }) => {
        loginPage = new LoginPage(page);
        await loginPage.goto();
    });

    test("login successfully", async () => {
        await loginPage.login("alaress", "alaress", true);
        await loginPage.verifyLoginSuccess();
    });

    test("login with an invalid username and password", async ({ db }) => {
        await loginPage.login("wrong", "wrong", true);
        await loginPage.verifyLoginFailure();

        // Test DB contents (Expect no user with username "wrong")
        const count = await db.countInDatabase("user", { username: "wrong" });
        expect(count).toBe(0);
    });

    test("impersonate a user via the account drop", async ({ tester }) => {
        const fullname = "Mr Jack Coles";
        await tester.impersonate(fullname);
        await loginPage.verifyLoginSuccess();

        tester.isGoingTo("verify that the correct user was impersonated");
        await loginPage.verifyCurrentUserIs(fullname);
    });

    test("impersonate a user via the modify user page", async ({ tester }) => {
        const fullname = "Mr Jack Coles";
        await tester.impersonateViaUserProfilePage(fullname);
        await loginPage.verifyLoginSuccess();

        tester.isGoingTo("verify that the correct user was impersonated");
        await loginPage.verifyCurrentUserIs(fullname);
    });

    test.afterEach(async () => {
        await loginPage.logout();
        await loginPage.verifyLogoutSuccess();
    });
});
