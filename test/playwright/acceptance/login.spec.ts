import { test, expect } from "@playwright/test";
import { LoginPage } from "../support/page/loginPage";

test.describe("Login functionality tests", () => {
    let loginPage: LoginPage;

    test.beforeEach(async ({ page }) => {
        loginPage = new LoginPage(page);
        await page.goto("/login/");
    });

    test("login successfully", async () => {
        await loginPage.login("alaress", "alaress", true);
        await loginPage.verifyLoginSuccess();
    });

    test("login with an invalid username and password", async () => {
        await loginPage.login("wrong", "wrong", true);
        await loginPage.verifyLoginFailure();
    });

    test.afterEach(async () => {
        await loginPage.logout();
        await loginPage.verifyLogoutSuccess();
    });
});
