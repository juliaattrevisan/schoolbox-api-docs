import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";

export default class Common {
    private page: Page;

    // sales data
    static readonly USER_JACKCOLES = "Mr Jack Coles";

    static readonly USER_ID_JACKCOLES = 22;

    static readonly USER_LOUISASMITH = "Louisa Smith";

    static readonly USER_ID_LOUISASMITH = 55;

    static readonly USER_JAMALALI = "Jamal Ali";

    static readonly USER_ID_JAMALALI = 60;

    static readonly CLASS_9AENG = "9A English";

    static readonly CLASS_ID_9AENG = 83;

    static readonly CLASS_ID_DEMO2 = 106;

    // TODO: Replace following with data-test selectors where possible

    /**
     * Content element selector. This is where most of Schoolbox's dynamic
     * content goes.
     */
    static readonly SELECTOR_CONTENT = "#content";

    /**
     * Footer selector. This is where the copyright, policy,
     * version number and support link go.
     */
    static readonly SELECTOR_FOOTER = "#footer";

    /**
     * Top menu selector. This is where the configurable top menu items go.
     */
    static readonly SELECTOR_TOP_MENU = "#top-menu";

    /**
     * Search icon selector
     */
    static readonly DATATEST_SEARCH_ICON = "search-input-button";

    /**
     * Search field selector; used as a text input for site searches.
     */
    static readonly SELECTOR_SEARCH = "#search-field";

    /**
     * Side menu selector; this is where the configurable side menu items go,
     * as well as the current user's groups, classes and links.
     *
     * (for some reason, this has no ID?)
     */
    static readonly SELECTOR_SIDEBAR = ".left-off-canvas-menu";

    /**
     * Notification center selector; this is where new notifications go.
     */
    static readonly SELECTOR_NOTIFICATIONS = "#msgs";

    /**
     * Date format as used in form inputs.
     *
     * This should match `config.date_local`; you can set it if you want.
     */
    static readonly FORMAT_INPUT_DATE = "d/m/Y";

    /**
     * Time format as used in form inputs.
     *
     * This should match `config.date_time`; you can set it if you want.
     */

    static readonly FORMAT_INPUT_TIME = "g:ia";

    /**
     * Date+time format as used in form inputs.
     */
    static readonly FORMAT_INPUT_DATETIME = `${Common.FORMAT_INPUT_DATE} ${Common.FORMAT_INPUT_TIME}`;

    /**
     * modify button on pages like news feed, pastoral record
     */
    static readonly ICON_MODIFY_PAGES = "a.icon-modify.no-text";

    /**
     * the currently open modal dialog
     * use this in combination with the other modal dialog selectors for better precision
     */
    static readonly SELECTOR_MODAL_DIALOG = "div[data-reveal].open";

    /**
     * confirm button on modal dialogs
     */
    static readonly SELECTOR_CONFIRM_DIALOG_BUTTON_CONFIRM = "a#confirm-dialog-confirm-button";

    /**
     * cancel button on modal dialogs
     */
    static readonly SELECTOR_CONFIRM_DIALOG_BUTTON_CANCEL = "a#confirm-dialog-cancel-button";

    /**
     * close x button on modal dialogs
     */
    static readonly SELECTOR_CLOSE_BUTTON_MODAL = ".close-reveal-modal";

    /**
     * add a comment element for learning moments, news, pastoral records
     */
    static readonly ADD_COMMENT_BUTTON = "button.show-ckeditor-button";

    /**
     * submit buttons for comment fields for learning moments, news, pastoral records
     */
    static readonly COMMENT_SUBMIT_BUTTON = "button[class=button submit]";

    /**
     * delete button on modal dialogs
     */
    static readonly MODAL_DELETE_BUTTON = "button.button.submit";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Ensures the page is loaded and ready to be interacted with (mostly)
     * This completes some specific checks to ensure that components such as the search bar are interactable
     *
     * Note, usually this isn't necessary as playwright's actionability checks are sufficient most of the time
     * However, in some cases, interacting with elements that load after the page has loaded can cause issues
     *
     * This function will wait for *most* of the page to be in a stable state before continuing
     * However, it is not a guarantee that all elements will be interactable (e.g. some vue component doing extra requests before loading)
     */
    async waitForPageLoad(): Promise<void> {
        // First wait for load to complete
        await this.page.waitForLoadState("load");

        // Are we on the login page? If so, skip the rest as it's irrelevant
        if ((await this.page.getByTestId("login-submit").count()) > 0) {
            return;
        }

        // The profile drop gets a data-test-ready="true" applied when its associated event listeners are ready
        const profileDrop = this.page.getByTestId("profile-drop");
        await expect(profileDrop).toHaveAttribute("data-test-ready", "true");

        // Make sure the search bar and profile drop are stable
        // uses "trial: true" to see if the elements are interactable, but not actually interact with them
        await profileDrop.click({ trial: true });
        await this.page.getByTestId("search-input-button").click({ trial: true });
    }

    /**
     * Generate a random string of text
     * @param length - the length of the string to generate
     * @returns - the generated string
     */
    static genText(length: number): string {
        const characters = "abcdefghijklmnopqrstuvwxyz.012345678!@#$%*&^()-+";
        let randomString = "";
        for (let i = 0; i < length; i += 1) {
            if (length > 8 && Math.floor(Math.random() * 7) === 5) {
                randomString += " ";
                i += 1;
            }
            randomString += characters[Math.floor(Math.random() * characters.length)];
        }
        return randomString;
    }

    /**
     * Changes a full name to a surname-first format
     * @param fullname - the full name to change
     * @returns - the surname-first name
     */
    static surnameFirst(fullname: string): string {
        const name = fullname.replace("Mr", "").replace("Mrs", "");
        const parts = name.split(" ");
        return `${parts[1]}, ${parts[0]}`;
    }

    /**
     * search by typing a text in the search bar
     * @param text - the text to search
     */
    async search(text: string): Promise<void> {
        await this.waitForPageLoad(); // When using the search bar, ensure it's interactable first
        await this.page.getByTestId(Common.DATATEST_SEARCH_ICON).click();
        await this.page.locator(Common.SELECTOR_SEARCH).fill(text);
        await this.page.getByTestId(Common.DATATEST_SEARCH_ICON).click();
    }

    /**
     * click a checkbox in the search results:
     * all, file, folder, textbox
     * @param type - the type of search result to click
     */
    async clickSearchTypeCheckbox(type: string): Promise<void> {
        await this.page.locator(`label[for="${type}"]`).click();
    }

    /**
     * clicks the submit button by locating the last element in the page
     */
    async clickSubmitButton(): Promise<void> {
        await this.page.locator("a.button.submit").last().click();
    }

    /**
     * clicks the confirm button on the dialogs
     */
    async clickConfirmButton(): Promise<void> {
        await this.page.locator(Common.SELECTOR_CONFIRM_DIALOG_BUTTON_CONFIRM).click();
    }

    /**
     * clicks the cancel button on the dialogs
     */
    async clickCancelButton(): Promise<void> {
        await this.page.locator(Common.SELECTOR_CONFIRM_DIALOG_BUTTON_CANCEL).click();
    }

    /**
     * clicks the close x button on the currently open modal dialog
     */
    async closeModal(): Promise<void> {
        // You can't close a reveal modal before it is in a fully opened state,
        // so wait for it to be visible and fully opaque before trying to close it
        const currentModal = this.page.locator(Common.SELECTOR_MODAL_DIALOG);
        await expect(currentModal).toBeVisible();
        await expect(currentModal).toHaveCSS("opacity", "1");

        await currentModal.locator(Common.SELECTOR_CLOSE_BUTTON_MODAL).click();
    }

    /**
     * click add a comment for learning moments, news, pastoral records etc
     */
    async clickAddComment(): Promise<void> {
        await this.page.locator(Common.ADD_COMMENT_BUTTON).first().click();
    }

    /**
     * click post comment for learning moments, news, pastoral care records
     */
    async clickSubmitComment(): Promise<void> {
        await this.page.locator(Common.COMMENT_SUBMIT_BUTTON).first().click();
    }

    /**
     * click the last element to confirm deletion in a modal dialog
     */
    async clickModalDeleteButton(): Promise<void> {
        await this.page.locator(Common.MODAL_DELETE_BUTTON).last().click();
    }

    /**
     * click the icon modify page
     */
    async clickIconModifyPage(): Promise<void> {
        await this.page.locator(Common.ICON_MODIFY_PAGES).click();
    }

    /**
     * Find a specific notification on the notifications page
     * Will change the page to /notifications
     * @param text - the text to search for
     * @param failIfNotFound - whether to fail if the notification is not found
     * @param retries - the number of times to retry if the notification is not found, defaults to 60 - which is up to 1 minute of retries
     * @returns - the notification locator
     */
    async findNotification(text: string, failIfNotFound: boolean, retries: number = 60): Promise<Locator> {
        await this.page.goto("/notifications");

        const notificationWithText = this.page
            .getByTestId("notification-list-notification-body")
            .filter({ hasText: text });

        // If it can't be found, try again after a short delay and refresh the page
        // Sometimes the notification takes a while to appear
        if (retries > 0 && (await notificationWithText.count()) === 0) {
            await this.page.waitForTimeout(1000);
            await this.page.reload();
            return this.findNotification(text, failIfNotFound, retries - 1);
        }

        if (failIfNotFound) {
            await expect(notificationWithText).toBeVisible();
        }

        return notificationWithText;
    }

    /**
     * Verifies that the notification exists
     * @param text - the text to verify
     */
    async assertNotifExists(text: string): Promise<void> {
        await this.findNotification(text, true);
    }

    /**
     * Verifies that the notification does not exist
     * @param text - the text to verify
     */
    async assertNotifNotExists(text: string): Promise<void> {
        const notification = await this.findNotification(text, false);
        await expect(notification).toHaveCount(0);
    }

    /**
     * Get today's date at 11:59 PM
     * @returns - the date object
     */
    static getTodayAtMidnight(): Date {
        const today = new Date();
        today.setHours(23, 59, 59, 999);
        return today;
    }

    /**
     * Convert a Date object to a string in the format "dd/mm/yyyy" or "dd/mm/yyyy hh:mm[am/pm]"
     * This is the expected format for date inputs around schoolbox
     * @param date - the date to convert
     * @param includeTime - whether to include the time in the string
     * @returns - the formatted date/datetime string
     */
    static formatDate(date: Date, includeTime: boolean): string {
        const zeroPad = (num: number): string => num.toString().padStart(2, "0");

        const day = zeroPad(date.getDate());
        const month = zeroPad(date.getMonth() + 1); // getMonth() returns 0-11
        const year = date.getFullYear();

        let formattedDate = `${day}/${month}/${year}`;

        if (includeTime) {
            const hours = zeroPad(date.getHours() % 12 || 12);
            const minutes = zeroPad(date.getMinutes());
            const ampm = date.getHours() >= 12 ? "pm" : "am";

            const formattedTime = `${hours}:${minutes}${ampm}`;
            formattedDate += ` ${formattedTime}`;
        }

        return formattedDate;
    }
}
