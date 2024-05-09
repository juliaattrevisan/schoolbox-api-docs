import type { Page } from "@playwright/test";
import { LoginPage } from "@schoolboxTest/playwright/support/page/loginPage";

export class AcceptanceTester {
    private page: Page;

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Logout the user and verify that the user is logged out
     */
    async logout() {
        const loginPage = new LoginPage(this.page);
        await loginPage.logout();
        await loginPage.verifyLogoutSuccess();
    }

    /**
     * Login a user with a given username and password and verify that the login was successful
     * Skips the "What's New" tour if it appears
     * @param { string } username - The username to login with
     * @param { string } password - The password to login with
     */
    async login(username: string, password: string) {
        const loginPage = new LoginPage(this.page);
        await loginPage.login(username, password, true);
        await loginPage.verifyLoginSuccess();

        await this.skipWhatsNewTour();
    }

    /**
     * Login as the Alaress user
     */
    async loginAsAdmin() {
        await this.login("alaress", "alaress");
    }

    /**
     * Skip the "What's New" tour if it exists
     */
    async skipWhatsNewTour() {
        const tourButtons = this.page.locator("div.introjs-tooltipbuttons a");
        const tourButtonCount = await tourButtons.count();
        if (tourButtonCount > 0) {
            await tourButtons.getByText("Finish").click();
        }
    }

    /**
     * Create an "I am going to " comment for debugging
     * @param { string } argumentation - The message to log
     */
    // eslint-disable-next-line class-methods-use-this
    isGoingTo(argumentation: string) {
        // eslint-disable-next-line no-console
        console.log("I am going to", argumentation);
    }
}
