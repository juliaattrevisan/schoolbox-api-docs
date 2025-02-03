import { expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import AwardAchievementWithdrawModal from "@schoolboxBadge/Award/vue/award-achievement-withdraw-modal.vue";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import Common from "@schoolboxTest/playwright/support/page/Common";
import { timeout } from "@schoolboxVue/components/toast/sbxToastEvent";

export default class AssessmentForm {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/learning/folders/{class_id}/assessments/new?type=quiz";

    static readonly SELECTOR_TITLE = "input#title";

    static readonly SELECTOR_BUTTON_CREATE = "assessment-form-button-submit-Create";

    static readonly SELECTOR_HOMEPAGE_TABS = "homepage-tabs";

    static readonly SELECTOR_WORKTYPE = "assessment-form-select-workType";

    // Constants for data-test selectors
    static readonly SELECTOR_FORM = "assessment-form";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Sets title
     * @param title - The title to set
     */
    async setTitle(title: string): Promise<void> {
        await this.page.locator(AssessmentForm.SELECTOR_TITLE).fill(title);
    }

    /**
     * Submits this form to create a quiz
     * Verifies that the modify contents if loaded
     */
    async create(): Promise<void> {
        await this.page.getByTestId(AssessmentForm.SELECTOR_BUTTON_CREATE).click();
        await this.page.waitForTimeout(1000);
    }
}
