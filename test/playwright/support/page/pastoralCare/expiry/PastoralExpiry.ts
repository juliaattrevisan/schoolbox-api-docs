/**
 * Utility class for the Pastoral expiry feature
 */

import type { Page, Locator } from "@playwright/test";
import { expect } from "@playwright/test";

export default class PastoralExpiry {
    private page: Page;

    static readonly URL_MANAGE_AWARD_ACHIEVEMENT = "/pastoral/management/audience/subtype/1/modify";

    static readonly URL_MANAGE_LEARNING_DIFFICULTIES = "/pastoral/management/audience/subtype/2/modify";

    static readonly URL_MANAGE_ATTENDANCE = "/pastoral/management/audience/type/2/modify";

    static readonly DATATEST_FLAG_SELECT = "audiences-modify-pastoral-flag-select";

    static readonly DATATEST_FLAG_COLOR_INPUT = "audiences-modify-pastoral-flag-color-input";

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Navigates to the Manage Academic › Award/Achievement type
     */
    async gotoManageAwardAchievement(): Promise<void> {
        await this.page.goto(PastoralExpiry.URL_MANAGE_AWARD_ACHIEVEMENT);
    }

    /**
     * Navigates to the Manage Academic › Learning Difficulties type
     */
    async gotoManageLearningDifficulties(): Promise<void> {
        await this.page.goto(PastoralExpiry.URL_MANAGE_AWARD_ACHIEVEMENT);
    }

    /**
     * Navigates to the Manage Attendance type
     */
    async gotoManageAttendance(): Promise<void> {
        await this.page.goto(PastoralExpiry.URL_MANAGE_ATTENDANCE);
    }

    /**
     * Selects a flag by label
     * @param label - The label of the flag to select
     */
    async selectFlagByLabel(label: string): Promise<void> {
        await this.page.getByTestId(PastoralExpiry.DATATEST_FLAG_SELECT).selectOption({ label });
    }

    /**
     * Sets the flag color
     * @param color - The color to set the flag to
     */
    async setFlagColor(color: string): Promise<void> {
        await this.page.getByTestId(PastoralExpiry.DATATEST_FLAG_COLOR_INPUT).fill(color);
    }
}
