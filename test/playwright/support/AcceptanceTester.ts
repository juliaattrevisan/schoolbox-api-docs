import path from "path";
import { readFileSync } from "fs";
import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";
import LoginPage from "@schoolboxTest/playwright/support/page/LoginPage";
import BaseTester from "@schoolboxTest/playwright/support/BaseTester";
import Common from "@schoolboxTest/playwright/support/page/Common";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";

/**
 * AcceptanceTester class to provide common functionality for acceptance tests
 *
 * Requires a Page object to interact with the browser, so this cannot be used in contexts such as .beforeAll or .afterAll
 *  as the Page and Context integrations are created on a per-test basis and not available in those hooks
 * @param { Page } page - The Playwright Page object to interact with the browser
 */
export default class AcceptanceTester extends BaseTester {
    private page: Page;

    public common: Common;

    constructor(page: Page) {
        super(); // Call the constructor of the BaseTester class
        this.page = page;

        // Provide common via tester for easy access
        this.common = new Common(page);
    }

    /**
     * Logout the user and verify that the user is logged out
     */
    async logout(): Promise<void> {
        // TEMPORARY: Force the test to fail to test CI
        expect("Forced failure").toEqual("");

        const loginPage = new LoginPage(this.page);
        await loginPage.logout();
        await loginPage.verifyLogoutSuccess();
    }

    /**
     * Login a user with a given username and password and verify that the login was successful
     * Skips the "What's New" tour if it appears
     * @param username - The username to login with
     * @param password - The password to login with
     */
    async login(username: string, password: string): Promise<void> {
        // TEMPORARY: Force the test to fail to test CI
        expect("Forced failure").toEqual("");

        const loginPage = new LoginPage(this.page);
        await loginPage.login(username, password, true);
        await this.skipWhatsNewTour();
        await loginPage.verifyLoginSuccess();
    }

    /**
     * Login as the Alaress user
     */
    async loginAsAdmin(): Promise<void> {
        await this.login("alaress", "alaress");
    }

    /**
     * Impersonate a user via user profile drop when alaress is presented in the drop
     * @param fullname - The full name of the user to impersonate
     */
    async impersonate(fullname: string): Promise<void> {
        this.isGoingTo(`impersonate a user by the name of ${fullname}`);
        await this.logout();
        await this.loginAsAdmin();
        await this.page.getByTestId("profile-drop").click();

        const accountContent = this.page.getByTestId("account-content");
        await accountContent.getByText(fullname).click();

        await this.skipWhatsNewTour();

        const loginPage = new LoginPage(this.page);
        await loginPage.verifyLoginSuccess();
        await loginPage.verifyCurrentUserIs(fullname);
    }

    /**
     * Impersonate a teacher (Jack Coles) - sales data
     */
    async impersonateTeacher(): Promise<void> {
        await this.impersonate("Jack Coles");
    }

    /**
     * Impersonate a user via the modify screen by passing their title and fullname
     * @param fullname - The full name of the user to impersonate
     */
    async impersonateViaModifyUserPage(fullname: string): Promise<void> {
        this.isGoingTo(`impersonate a user by the name of ${fullname}`);
        await this.logout();
        await this.loginAsAdmin();

        await this.searchForUser(fullname);
        await this.common.waitForPageLoad();

        await this.page.getByTestId("user-profile-modify").click();

        this.isGoingTo("click the options button and impersonate the user");
        await this.page.getByTestId("sbx-pop-out-header-options-button").click();
        await this.page.getByTestId("admin-user-modify-impersonate").click();

        await this.skipWhatsNewTour();

        const loginPage = new LoginPage(this.page);
        await loginPage.verifyLoginSuccess();
        await loginPage.verifyCurrentUserIs(fullname);
    }

    /**
     * Search for a user using the search bar
     * @param fullname - The full name of the user to search for
     */
    async searchForUser(fullname: string): Promise<void> {
        this.isGoingTo(`search for a user by the name of ${fullname}`);
        await this.page.getByTestId("search-input-button").click();
        // await this.page.getByTestId("search-input-field").fill(fullname);
        await this.page.getByTestId("search-input-field").click();
        await this.page.keyboard.type(fullname, { delay: 500 });
        await this.page.getByTestId(`header-search-user-result-link-${fullname}`).click();

        this.isGoingTo("expect to be redirected to the user profile page");
        await expect(this.page.getByTestId("user-profile-name")).toHaveText(fullname);
    }

    /**
     * Skip the "What's New" tour if it exists
     */
    async skipWhatsNewTour(): Promise<void> {
        const tourButtons = this.page.locator("div.introjs-tooltipbuttons a");
        const tourButtonCount = await tourButtons.count();
        if (tourButtonCount > 0) {
            await tourButtons.getByText("x").click();
        }
    }

    /**
     * Fill in CKEditor5 based on its ID
     * @param editorId - The ID of the CKEditor5 instance
     * @param content - The content to fill the editor with
     */
    async fillCKEditorById(editorId: string, content: string): Promise<void> {
        // There should be at least one ckeditor, somewhere on the page, first.
        // The actual element of the ckeditor has no direct correlation to the editorId, but if one exists then
        // we can proceed with the next checks to find the actual editor we want
        await expect(this.page.locator("div.ck.ck-editor").first()).toHaveCount(1);

        // Wait for the editor to be ready
        await this.page.waitForFunction(
            ([_editorId]) => {
                if (window.sbProps === undefined || window.sbProps.editors === undefined) {
                    return false;
                }
                if (window.sbProps.editors[_editorId] === undefined) {
                    return false;
                }
                return true;
            },
            [editorId],
        );

        // Focus the editor, and clear all content
        await this.page.evaluate(
            ([_editorId]) => {
                window.sbProps.editors[_editorId]!.focus();
                window.sbProps.editors[_editorId]!.setData("");
            },
            [editorId],
        );

        // Fill the editor
        await this.page.keyboard.type(content);
    }

    /**
     * Fill in CKEditor5 based on a Locator
     * The relevant locator should have a ID set
     * @param locator - The locator representing the initial Textarea the editor was created from
     * @param content - The content to fill the editor with
     */
    async fillCKEditorByLocator(locator: Locator, content: string): Promise<void> {
        await expect(locator).toHaveCount(1);
        const editorId = await locator.getAttribute("id");
        expect(editorId).not.toBeNull();
        await this.fillCKEditorById(editorId as string, content);
    }

    /**
     * Attach a file to an attachzone, by simulating drag and drop
     * @param db - The DatabaseHelper instance
     * @param locator - the locator of the attachzone (usually an element with class "attachzone")
     * @param filePath - the path to the file to attach
     */
    async dragAndDropFile(db: DatabaseHelper, locator: Locator, filePath: string): Promise<void> {
        const buffer = readFileSync(filePath);
        const fileName = path.basename(filePath);
        const fileExtension = path.extname(filePath).slice(1);

        // Fetch the MIME type from the instance's file extension list
        this.isGoingTo(`Find the MIME type for the file extension: ${fileExtension}`);
        const fileTypeID = await db.grabFromDatabase("allowedFileExtension", "fileTypeID", {
            extension: fileExtension,
        });
        if (typeof fileTypeID !== "number") {
            throw new Error(`File type ID not found for extension: ${fileExtension}`);
        }
        const mimeType = await db.grabFromDatabase("allowedFileTypes", "mime", { fileTypeID });
        if (typeof mimeType !== "string") {
            throw new Error(`MIME type not found for file type ID: ${fileTypeID}`);
        }

        // Create a DataTransfer object with the file to simulate drag and drop
        this.isGoingTo(`Simulate drag and drop of file: ${fileName} with MIME type: ${mimeType}`);
        const dataTransfer = await this.page.evaluateHandle(
            ({ _data, _fileName, _mimeType }) => {
                const dt = new DataTransfer();
                // We need to include the mime type in the file object, otherwise the file will not be accepted
                const file = new File([Uint8Array.from(atob(_data), (c) => c.charCodeAt(0))], _fileName, {
                    type: _mimeType,
                });
                dt.items.add(file);
                return dt;
            },
            { _data: buffer.toString("base64"), _fileName: fileName, _mimeType: mimeType },
        );

        // Then, dispatch a drop event on the dropzone element
        await locator.dispatchEvent("drop", { dataTransfer });
    }
}
