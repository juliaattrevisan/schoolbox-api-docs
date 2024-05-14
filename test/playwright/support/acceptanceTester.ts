import { spawn } from "child_process";
import { existsSync } from "fs";
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
     * Impersonate a user via user profile drop when alaress is presented in the drop
     * @param { string } fullname - The full name of the user to impersonate
     */
    async impersonate(fullname: string) {
        this.isGoingTo(`impersonate a user by the name of ${fullname}`);
        await this.logout();
        await this.loginAsAdmin();
        await this.page.getByTestId("profile-drop").click();

        const accountContent = this.page.getByTestId("account-content");
        await accountContent.getByText(fullname).click();

        await this.skipWhatsNewTour();
    }

    /**
     * Impersonate a teacher (Jack Coles) - sales data
     */
    async impersonateTeacher() {
        await this.impersonate("Jack Coles");
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
     * Executes a shell command and returns the result.
     * Looks for a /usr/share/schoolbox directory and if not found, attempts to run the command via `vagrant ssh -c '...'`
     * @param { string } command - The command to execute.
     * @returns { Promise<string> } The stdout from the command.
     * @throws { Error } If the command fails.
     */
    // eslint-disable-next-line class-methods-use-this
    async runShellCommand(command: string): Promise<string> {
        // Try to run in vagrant if the schoolbox directory does not exist
        if (!existsSync("/usr/share/schoolbox") && !command.startsWith("vagrant ssh -c ")) {
            const vagrantCommand = `vagrant ssh -c "${command.replace(/"/g, '\\"')}"`;
            return this.runShellCommand(vagrantCommand);
        }

        return new Promise((resolve, reject) => {
            const parts = command.split(" ");
            const cmd = parts[0];
            const args = parts.slice(1);

            const child = spawn(cmd, args, { shell: true });

            let output = ""; // This will collect all the stdout

            // Handle stdout data
            child.stdout.on("data", (data: string) => {
                process.stdout.write(data);
                output += data.toString();
            });

            // Handle stderr data
            child.stderr.on("data", (data: string) => {
                process.stdout.write(data);
                output += data.toString();
            });

            // Handle completion
            child.on("close", (code) => {
                if (code === 0) {
                    resolve(output);
                } else {
                    reject(new Error(`Command failed with exit code ${code}`));
                }
            });
        });
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
