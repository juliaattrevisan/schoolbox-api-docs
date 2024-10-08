import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";

export default class LoginPage {
    public static LOGIN_URL = "/login";

    public static LOGOUT_URL = "/logout";

    private page: Page;

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigate to the login page
     */
    async goto(): Promise<void> {
        await this.page.goto(LoginPage.LOGIN_URL);
        await this.page.waitForLoadState("load");
    }

    /**
     * Logout the user
     */
    async logout(): Promise<void> {
        await this.page.goto(LoginPage.LOGOUT_URL);
    }

    /**
     * Verify that the user is currently logged out
     */
    async verifyLogoutSuccess(): Promise<void> {
        await expect(this.page.getByTestId("login-submit")).toBeVisible();
    }

    /**
     * Verify the name of the currently logged in user
     * @param fullname - The full name of the user to verify
     */
    async verifyCurrentUserIs(fullname: string): Promise<void> {
        const fullNameMatch = new RegExp(`^.*${fullname}.*$`);
        await expect(this.page.getByTestId("profile-drop")).toHaveAttribute("title", fullNameMatch);
    }

    /**
     * Login a user with a given username and password
     * @param username - The username to login with
     * @param password - The password to login with
     * @param rememberMe - Whether to remember the user or not [optional, default=false]
     */
    async login(username: string, password: string, rememberMe: boolean = false): Promise<void> {
        await this.page.goto(LoginPage.LOGIN_URL);

        await this.page.getByTestId("login-username").fill(username);
        await this.page.getByTestId("login-password").fill(password);

        if (rememberMe) {
            await this.page.getByText("Remember me").check();
        } else {
            await this.page.getByText("Remember me").uncheck();
        }

        await this.page.getByTestId("login-submit").click();
    }

    /**
     * Verify that the user is currently logged in
     */
    async verifyLoginSuccess(): Promise<void> {
        // Ensure the page and JavaScript has fully loaded and is ready for interaction
        // An issue was found where the profile drop was not clickable for around 400ms
        //  after the page loaded as miscellaneous.js had not created the event listeners yet
        await this.page.getByTestId("profile-drop").waitFor({ state: "visible" });
        await this.page.waitForLoadState("load");

        await expect(this.page.getByTestId("profile-drop")).toBeVisible();
    }

    /**
     * Verify that the login failed
     */
    async verifyLoginFailure(): Promise<void> {
        await expect(this.page.getByTestId("login-error-msg")).toHaveText(
            "The username or password you typed is incorrect, please try again.",
        );
    }
}
