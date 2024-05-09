import { test, expect } from "@schoolboxTest/playwright/support/fixtures";
import { LoginPage } from "@schoolboxTest/playwright/support/page/loginPage";

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

    test.afterEach(async () => {
        await loginPage.logout();
        await loginPage.verifyLogoutSuccess();
    });
});
