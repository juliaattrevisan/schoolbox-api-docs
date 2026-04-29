/**
 * Utility class for the Pastoral expiry feature
 */

import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import CreatePastoralRecord from "@schoolboxTest/playwright/support/page/pastoralCare/CreatePastoralRecord";

export default class PastoralExpiry {
    // Playwright page instance used to interact with the manage record settings UI.
    private page: Page;

    // Shared acceptance test helpers for common UI interactions and CKEditor input.
    private tester: AcceptanceTester;

    // Helper page object for reusing pastoral record action selectors.
    private createPastoralRecordPage: CreatePastoralRecord;

    // Route for the Award/Achievement subtype settings page.
    static readonly URL_AWARD_ACHIEVEMENT = "/pastoral/management/audience/subtype/1/modify";

    // Route for the Learning Difficulties subtype settings page.
    static readonly URL_LEARNING_DIFFICULTIES = "/pastoral/management/audience/subtype/2/modify";

    // Route for the Attendance type settings page.
    static readonly URL_ATTENDANCE = "/pastoral/management/audience/type/2/modify";

    // Route for the Bullying type settings page.
    static readonly URL_BULLYING = "/pastoral/management/audience/type/4/modify";

    // Test id for the pastoral flag dropdown.
    static readonly DATA_TEST_FLAG_SELECT = "audiences-modify-pastoral-flag-select";

    // Test id for the pastoral flag colour picker input.
    static readonly DATA_TEST_FLAG_COLOR_INPUT = "audiences-modify-pastoral-flag-color-input";

    // Test id for the default expiry dropdown.
    static readonly DATA_TEST_DEFAULT_EXPIRY_SELECT = "audiences-modify-pastoral-default-expiry-select";

    // Test id for the apply default expiry checkbox.
    static readonly DATA_TEST_APPLY_DEFAULT_EXPIRY_CHECKBOX = "audiences-modify-apply-default-expiry";

    // Test id for the contribute to differentiation profile checkbox.
    static readonly DATA_TEST_CONTRIBUTE_DIFFERENTIATION_PROFILE_CHECKBOX =
        "audiences-modify-contribute-differentiation-profile";

    // Test id for the save button on the manage settings page.
    static readonly DATA_TEST_MANAGE_SAVE_BUTTON = "audiences-modify-pastoral-save-button";

    // Selector for the visibility role link that opens the add role modal.
    static readonly SELECTOR_VISIBILITY_ROLE_LINK = 'a[title="Add Role"]';

    // Test id for the lock visibility toggle.
    static readonly DATA_TEST_LOCK_VISIBILITY_TOGGLE = "pastoral-settings-lock-visibility-toggle";

    // Selector for the selectized input used to assign default actions.
    static readonly SELECTOR_DEFAULT_ACTIONS_INPUT = "input#pastoral-default-actions-selectized";

    // Selector for the deny parent visibility checkbox label.
    static readonly SELECTOR_DENY_PARENT = 'label[for="roleDenyrole-type-parent"]';

    // Selector template for confidential comment role checkboxes.
    static readonly SELECTOR_CONFIDENTIAL_COMMENT = 'label[for="rolePrivate{id}"]';

    // Selector for the sentiment dropdown.
    static readonly SELECTOR_SENTIMENT_DROPDOWN = "#sentiment";

    // CKEditor id for the entry tip field.
    static readonly SELECTOR_ENTRY_TIP_ID = "entryTip";

    // CKEditor id for the general template field.
    static readonly SELECTOR_GENERAL_TEMPLATE_ID = "template";

    // CKEditor id for the private template field.
    static readonly SELECTOR_PRIVATE_TEMPLATE_ID = "templatePrivate";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
        this.createPastoralRecordPage = new CreatePastoralRecord(page);
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
     * Click apply to differentiation profiles checkbox
     */
    async clickApplyToDifferentiationProfiles(): Promise<void> {
        await this.page.getByTestId(PastoralExpiry.DATA_TEST_CONTRIBUTE_DIFFERENTIATION_PROFILE_CHECKBOX).click();
    }

    /**
     * Click save button
     */
    async clickSave(): Promise<void> {
        await this.page.getByTestId(PastoralExpiry.DATA_TEST_MANAGE_SAVE_BUTTON).click();
    }

    /**
     * Add one or more available actions
     * @param actionNames - The action names to add
     */
    async addAvailableActions(actionNames: string[]): Promise<void> {
        await this.addActions(CreatePastoralRecord.SELECTOR_ACTIONS_INPUT, actionNames);
    }

    /**
     * Add one or more default actions
     * @param actionNames - The action names to add
     */
    async addDefaultActions(actionNames: string[]): Promise<void> {
        await this.addActions(PastoralExpiry.SELECTOR_DEFAULT_ACTIONS_INPUT, actionNames);
    }

    /**
     * Check the available actions selectize contains the expected action names
     * @param actionNames - The action names to check
     */
    async expectAvailableActions(actionNames: string[]): Promise<void> {
        await this.expectActions(CreatePastoralRecord.SELECTOR_ACTIONS_INPUT, actionNames);
    }

    /**
     * Check the default actions selectize contains the expected action names
     * @param actionNames - The action names to check
     */
    async expectDefaultActions(actionNames: string[]): Promise<void> {
        await this.expectActions(PastoralExpiry.SELECTOR_DEFAULT_ACTIONS_INPUT, actionNames);
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

    /**
     * Selects a sentiment by value: positive, neutral, negative
     * @param option - The value of the sentiment to select: positive, neutral, negative
     */
    async selectSentiment(option: string): Promise<void> {
        await this.page.locator(PastoralExpiry.SELECTOR_SENTIMENT_DROPDOWN).click();
        await this.page.locator(PastoralExpiry.SELECTOR_SENTIMENT_DROPDOWN).selectOption({ label: option });
    }

    /**
     * fill the entry tip text
     * @param tip - The tip text to fill
     */
    async fillEntryTip(tip: string): Promise<void> {
        await this.tester.fillCKEditorById(PastoralExpiry.SELECTOR_ENTRY_TIP_ID, tip);
    }

    /**
     * fill the general template text
     * @param template - The general template text to fill
     */
    async fillGeneralTemplate(template: string): Promise<void> {
        await this.tester.fillCKEditorById(PastoralExpiry.SELECTOR_GENERAL_TEMPLATE_ID, template);
    }

    /**
     * fill the private template text
     * @param template - The private template text to fill
     */
    async fillPrivateTemplate(template: string): Promise<void> {
        await this.tester.fillCKEditorById(PastoralExpiry.SELECTOR_PRIVATE_TEMPLATE_ID, template);
    }

    /**
     * Add one or more actions to a selectize field
     * @param inputSelector - The selectize input selector
     * @param values - The values to add
     */
    private async addActions(inputSelector: string, values: string[]): Promise<void> {
        for (const value of values) {
            await this.page.keyboard.press("Escape");
            await this.page.locator(inputSelector).click();
            await this.page.locator(".selectize-dropdown-content:visible").getByText(value, { exact: true }).click();
            await this.page.keyboard.press("Escape");
        }
    }

    /**
     * Check a selectize field contains the expected actions
     * @param inputSelector - The selectize input selector
     * @param values - The values to check
     */
    private async expectActions(inputSelector: string, values: string[]): Promise<void> {
        const input = this.page.locator(inputSelector);
        const container = input.locator("xpath=ancestor::div[contains(@class,'selectize-input')]");

        for (const value of values) {
            await expect(container).toContainText(value);
        }
    }
}
