import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";

export default class Badges {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/badge";

    static readonly BUTTON_ADD = "a.icon-add.button";

    static readonly DROPDOWN_DELETE = "a.icon-delete";

    static readonly BUTTON_CONFIRM_DELETE = "a.button.submit";

    static readonly DATATEST_LIST_BOX_GOAL_ACHIEVEMENT = "ss-query-input";

    static readonly DATATEST_SELECTED_STUDENT = "ss-results-item-container"; // append -[userId]

    static readonly DATA_TEST_MODAL_BUTTON_SET_GOAL_ACHIEVEMENT = "badge-award-confirm";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the learning badges page
     */
    async goto(): Promise<void> {
        await this.page.goto(Badges.URL);
    }

    /**
     * wait Create Badge button to be presented
     */

    async waitAddBadgeButtonToBePresented(): Promise<void> {
        await this.page.waitForSelector(Badges.BUTTON_ADD);
    }

    /**
     * click Create Badge button
     */
    async clickAddBadgeButton(): Promise<void> {
        await this.page.click(Badges.BUTTON_ADD);
    }

    /**
     * verify badge name
     * @param { string } text - The text to verify
     */

    async verifyTextIsPresented(text: string): Promise<void> {
        await expect(this.page.locator("#container")).toContainText(text);
    }

    /**
     * Click a badge dropdown
     * @param badgeNumber - The badge number
     */
    async clickBadgeDropdown(badgeNumber: number): Promise<void> {
        await this.page.getByTestId(`sbx-pop-out-badge-${badgeNumber}-dropdown`).click();
    }

    /**
     * click an option in the dropdown (Award Achievement, Set Goal, etc)
     * options are: achievement, goal, withdraw-achievement, close, modify, delete
     * @param dropdownOption - The dropdown option
     */
    async clickDropdownOptionLastElement(dropdownOption: string): Promise<void> {
        await this.page.click(`a.icon-${dropdownOption}`);
    }

    /**
     * click an option in the dropdown (Award Achievement, Set Goal, etc)
     * options are: achievement, goal, withdraw-achievement, close, modify, delete
     * @param badgeId - The badge number
     * @param dropdownOption - The dropdown option
     */
    async clickDropdownOptionFirstElement(badgeId: number, dropdownOption: string): Promise<void> {
        await this.page.click(`#badge-${badgeId}-dropdown a.icon-${dropdownOption}`);
    }

    /**
     * click an option Modify in the dropdown
     * @param badgeNumber - The badge number
     */
    async clickDropdownModify(badgeNumber: number): Promise<void> {
        await this.page.click(`a[href="/learning/badge/${badgeNumber}/update"]`);
    }

    /**
     * click an option Delete in the dropdown
     */
    async clickDropdownDelete(): Promise<void> {
        await this.page.click(Badges.DROPDOWN_DELETE);
    }

    /**
     * click Delete button on confirmation dialog
     */
    async clickConfirm(): Promise<void> {
        await this.page.locator(Badges.BUTTON_CONFIRM_DELETE).last().click();
    }

    /**
     * search and select a student on the set goal or award achievement modal
     * @param studentName - The student name
     */
    async selectStudentSetGoalOrAchievement(studentName: string): Promise<void> {
        const openModal = await this.tester.common.waitForModalToBeOpen();
        await openModal.getByTestId(Badges.DATATEST_LIST_BOX_GOAL_ACHIEVEMENT).fill(studentName);
        // Find the student in the list and click it (need to generalise the data-test as it's dynamic)
        await openModal
            .locator(`[data-test^="${Badges.DATATEST_SELECTED_STUDENT}"]`) // (starts with)
            .filter({
                hasText: studentName,
            })
            .click();
        // Click on the <h2> of the modal to make sure the search select is not focused anymore
        await openModal.locator("h2").click();
    }

    /**
     * click Set Goal on the modal
     * @param set - The set to click
     */
    async clickSetGoalorAwardAchievement(set: string): Promise<void> {
        await expect(
            this.page.getByTestId(Badges.DATA_TEST_MODAL_BUTTON_SET_GOAL_ACHIEVEMENT).filter({ hasText: set }),
        ).toBeEnabled();
        await this.page
            .getByTestId(Badges.DATA_TEST_MODAL_BUTTON_SET_GOAL_ACHIEVEMENT)
            .filter({ hasText: set })
            .click();
        await this.tester.common.waitForModalToBeClosed();
    }

    /**
     * click submit button on the evidence page
     */
    async clickEvidenceSubmitButton(): Promise<void> {
        await this.page.click("input[type=submit]");
    }
}
