import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { Page, expect } from "@playwright/test";

export default class Badges {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/badge";

    static readonly BUTTON_ADD = "a.icon-add.button";

    static readonly DROPDOWN_DELETE = "a.icon-delete";

    static readonly BUTTON_CONFIRM_DELETE = "a.button.submit";

    static readonly LIST_BOX_GOAL = "input[aria-controls='vs2__listbox']";

    static readonly LIST_BOX_AWARD = "input[aria-controls='vs1__listbox']";

    static readonly USER_DROPDOWN = "ul[class='vs__dropdown-menu']";

    static readonly SELECTED_STUDENT = "span.d-center";

    static readonly DATA_TEST_MODAL_BUTTON_SET_GOAL_ACHIEVEMENT = "badge-award-confirm";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the learning badges page
     */
    async goto() {
        await this.page.goto(Badges.URL);
    }

    /**
     * wait Create Badge button to be presented
     */

    async waitAddBadgeButtonToBePresented() {
        await this.page.waitForSelector(Badges.BUTTON_ADD);
    }

    /**
     * click Create Badge button
     */
    async clickAddBadgeButton() {
        await this.page.click(Badges.BUTTON_ADD);
    }

    /**
     * verify badge name
     * @param { string } text - The text to verify
     */

    async verifyTextIsPresented(text: string) {
        await expect(this.page.locator("#container")).toContainText(text);
    }

    /**
     * Click a badge dropdown
     * @param { number } badgeNumber - The badge number
     */
    async clickBadgeDropdown(badgeNumber: number) {
        await this.page.click(`[data-test=sbx-pop-out-badge-${badgeNumber}-dropdown]`);
    }

    /**
     * click an option in the dropdown (Award Achievement, Set Goal, etc)
     * options are: achievement, goal, withdraw-achievement, close, modify, delete
     * @param { string } dropdownOption - The dropdown option
     */
    async clickDropdownOptionLastElement(dropdownOption: string) {
        await this.page.click(`a.icon-${dropdownOption}`);
    }

    /**
     * click an option in the dropdown (Award Achievement, Set Goal, etc)
     * options are: achievement, goal, withdraw-achievement, close, modify, delete
     * @param { number } badgeId - The badge number
     * @param { string } dropdownOption - The dropdown option
     */
    async clickDropdownOptionFirstElement(badgeId: number, dropdownOption: string) {
        await this.page.click(`#badge-${badgeId}-dropdown a.icon-${dropdownOption}`);
    }

    /**
     * click an option Modify in the dropdown
     * @param { number } badgeNumber - The badge number
     */
    async clickDropdownModify(badgeNumber: number) {
        await this.page.click(`a[href="/learning/badge/${badgeNumber}/update"]`);
    }

    /**
     * click an option Delete in the dropdown
     */
    async clickDropdownDelete() {
        await this.page.click(Badges.DROPDOWN_DELETE);
    }

    /**
     * click Delete button on confirmation dialog
     */
    async clickConfirm() {
        await this.page.locator(Badges.BUTTON_CONFIRM_DELETE).last().click();
    }

    /**
     * search and select a student on goal the modal
     * @param { string } studentName - The student name
     */
    async selectStudentSetGoal(studentName: string) {
        await this.page.fill(Badges.LIST_BOX_GOAL, studentName);
        await this.page.waitForSelector(Badges.USER_DROPDOWN);
        await this.page.press(Badges.LIST_BOX_GOAL, "Enter");
        await this.page.click(Badges.SELECTED_STUDENT);
        await this.page.keyboard.press("Tab");
    }

    /**
     * search and select a student on goal the modal
     * @param { string } studentName - The student name
     */
    async selectStudentAwardAchievement(studentName: string) {
        await this.page.fill(Badges.LIST_BOX_AWARD, studentName);
        await this.page.waitForSelector(Badges.USER_DROPDOWN);
        await this.page.press(Badges.LIST_BOX_AWARD, "Enter");
        await this.page.click(Badges.SELECTED_STUDENT);
        await this.page.keyboard.press("Tab");
    }

    /**
     * click Set Goal on the modal
     * @param { string } set - The set to click
     */
    async clickSetGoalorAwardAchievement(set: string) {
        await expect(
            this.page.getByTestId(Badges.DATA_TEST_MODAL_BUTTON_SET_GOAL_ACHIEVEMENT).filter({ hasText: set }),
        ).toBeEnabled();
        await this.page
            .getByTestId(Badges.DATA_TEST_MODAL_BUTTON_SET_GOAL_ACHIEVEMENT)
            .filter({ hasText: set })
            .click();
    }

    /**
     * click submit button on the evidence page
     */
    async clickEvidenceSubmitButton() {
        await this.page.click("input[type=submit]");
    }
}
