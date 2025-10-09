/**
 * Utility class for the Pastoral expiry feature
 */

import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";

export default class PastoralExpiry {
    private page: Page;

    static readonly URL_AWARD_ACHIEVEMENT = "/pastoral/management/audience/subtype/1/modify";

    static readonly URL_LEARNING_DIFFICULTIES = "/pastoral/management/audience/subtype/2/modify";

    static readonly URL_ATTENDANCE = "/pastoral/management/audience/type/2/modify";

    static readonly URL_BULLYING = "/pastoral/management/audience/type/4/modify";

    static readonly DATA_TEST_FLAG_SELECT = "audiences-modify-pastoral-flag-select";

    static readonly DATA_TEST_FLAG_COLOR_INPUT = "audiences-modify-pastoral-flag-color-input";

    static readonly DATA_TEST_DEFAULT_EXPIRY_SELECT = "audiences-modify-pastoral-default-expiry-select";

    static readonly DATA_TEST_APPLY_DEFAULT_EXPIRY_CHECKBOX = "audiences-modify-apply-default-expiry";

    static readonly DATA_TEST_MANAGE_SAVE_BUTTON = "audiences-modify-pastoral-save-button";

    static readonly SELECTOR_VISIBILITY_ROLE_LINK = 'a[title="Add Role"]';

    static readonly DATA_TEST_LOCK_VISIBILITY_TOGGLE = "pastoral-settings-lock-visibility-toggle";

    static readonly SELECTOR_DENY_PARENT = 'label[for="roleDenyrole-type-parent"]';

    static readonly SELECTOR_CONFIDENTIAL_COMMENT = 'label[for="rolePrivate{id}"]';

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to the Manage Academic › Award/Achievement type
     */
    async gotoAwardAchievement(): Promise<void> {
        await this.page.goto(PastoralExpiry.URL_AWARD_ACHIEVEMENT);
    }

    /**
     * Navigates to the Manage Academic › Learning Difficulties type
     */
    async gotoLearningDifficulties(): Promise<void> {
        await this.page.goto(PastoralExpiry.URL_LEARNING_DIFFICULTIES);
    }

    /**
     * Navigates to the Manage Attendance type
     */
    async gotoAttendance(): Promise<void> {
        await this.page.goto(PastoralExpiry.URL_ATTENDANCE);
    }

    /**
     * Navigates to the Bullying type
     */
    async gotoBullying(): Promise<void> {
        await this.page.goto(PastoralExpiry.URL_BULLYING);
    }

    /**
     * Selects a flag by label
     * @param label - The label of the flag to select
     */
    async selectFlag(label: string): Promise<void> {
        await this.page.getByTestId(PastoralExpiry.DATA_TEST_FLAG_SELECT).selectOption({ label });
    }

    /**
     * Sets the flag color (we just use the first swatch for now)
     * I was having trouble just using .fill() to set the color,
     * so I'm using the swatch button instead
     */
    async fillFlagColor(): Promise<void> {
        await this.page.getByTestId(PastoralExpiry.DATA_TEST_FLAG_COLOR_INPUT).click();
        await this.page.getByRole("button", { name: "Color swatch rgb(0, 102, 176)" }).click();
    }

    /**
     * Selects the default expiry by label
     * @param label - The label of the default expiry to select
     */
    async selectDefaultExpiry(label: string): Promise<void> {
        await this.page.getByTestId(PastoralExpiry.DATA_TEST_DEFAULT_EXPIRY_SELECT).selectOption({ label });
    }

    /**
     * Expects the default expiry to be a certain value
     * @param value - The value of the default expiry to select
     */
    async expectDefaultExpiryToBe(value: string): Promise<void> {
        await expect(this.page.getByTestId(PastoralExpiry.DATA_TEST_DEFAULT_EXPIRY_SELECT)).toHaveValue(value);
    }

    /**
     * Click apply default expiry checkbox
     */
    async clickApplyDefaultExpiry(): Promise<void> {
        await this.page.getByTestId(PastoralExpiry.DATA_TEST_APPLY_DEFAULT_EXPIRY_CHECKBOX).click();
    }

    /**
     * Click save button
     */
    async clickSave(): Promise<void> {
        await this.page.getByTestId(PastoralExpiry.DATA_TEST_MANAGE_SAVE_BUTTON).click();
    }

    /**
     * Click the visibility role link to open the modal
     */
    async clickVisibilityRoleLink(): Promise<void> {
        await this.page.locator(PastoralExpiry.SELECTOR_VISIBILITY_ROLE_LINK).click();
    }

    /**
     * click one or more roles in the visibility modal: Administration, Teacher, parent, student
     * @param roles - array of role names to click
     */
    async clickRolesInModal(roles: string[]): Promise<void> {
        const rolesModal = this.page.locator("ul.information-list");

        for (const role of roles) {
            await rolesModal.getByTestId(`modal-add-pastoral-role-${role}`).click();
        }
    }

    /**
     * Click the lock visibility toggle
     */
    async clickLockVisibilityToggle(): Promise<void> {
        await this.page.getByTestId(PastoralExpiry.DATA_TEST_LOCK_VISIBILITY_TOGGLE).click();
    }

    /**
     * click the deny parent checkbox
     */
    async clickDenyParent(): Promise<void> {
        await this.page.locator(PastoralExpiry.SELECTOR_DENY_PARENT).click();
    }

    /**
     * click general confidential comment checkbox: 1 for administration, 2 for teacher, 3 for parent, 4 for student
     * @param id - the id of the checkbox to click
     */
    async clickConfidentialComment(id: number | string): Promise<void> {
        await this.page.locator(PastoralExpiry.SELECTOR_CONFIDENTIAL_COMMENT.replace("{id}", id.toString())).click();
    }
}
