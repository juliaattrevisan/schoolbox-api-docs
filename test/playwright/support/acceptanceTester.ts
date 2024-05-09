import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";
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

    //     /**
    //  * Fill in CKEditor 5
    //  * @param $elementId
    //  * @param $content
    //  * @throws Exception
    //  */
    //     public function fillCkEditorById($elementId, $content): void
    //     {
    //         $this->executeJS(sprintf('window.sbProps.editors["%s"].setData("<p>%s</p>");', $elementId, $content));
    //     }

    /**
     * Fill in CKEditor5 based on its ID
     * @param { string } editorId - The ID of the CKEditor5 instance
     * @param { string } content - The content to fill the editor with
     */
    async fillCKEditorById(editorId: string, content: string) {
        await this.page.evaluate(
            ([_editorId, _content]) => {
                window.sbProps.editors[_editorId].setData(`<p>${_content}</p>`);
            },
            [editorId, content],
        );
    }

    /**
     * Fill in CKEditor5 based on a Locator
     * The relevant locator should have a ID set
     * @param { Locator } locator - The locator representing the initial Textarea the editor was created from
     * @param { string } content - The content to fill the editor with
     */
    async fillCKEditorByLocator(locator: Locator, content: string) {
        await expect(locator).toHaveCount(1);
        const editorId = await locator.getAttribute("id");
        expect(editorId).not.toBeNull();
        await this.fillCKEditorById(editorId as string, content);
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
