import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";

export default class CreateLessonPlan {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly CREATE_BUTTON = "input[type=submit][value=Create]";

    static readonly LINK_ACTIVITY = "link existing activities";

    static readonly DATA_TEST_LINK_ACTIVITY_BUTTON = "lesson-plan-link-activities-link";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * type the lesson plan name
     * @param lessonPlanName - the name of the lesson plan
     */
    async typeName(lessonPlanName: string): Promise<void> {
        await this.page.fill("input#title", lessonPlanName);
    }

    /**
     * click the create button
     */
    async clickCreateButton(): Promise<void> {
        await this.page.locator(CreateLessonPlan.CREATE_BUTTON).click();
    }

    /**
     * click the link activity button
     */
    async clickLinkActivity(): Promise<void> {
        await this.page.getByTestId(CreateLessonPlan.DATA_TEST_LINK_ACTIVITY_BUTTON).click();
    }

    /**
     * Clicks a unit expand arrow
     * @param unitNumber - The unit number to click
     */
    async clickUnitMenu(unitNumber: number): Promise<void> {
        await this.page.locator(`a[data-collapser="collap-unit-${unitNumber}"]`).click();
        await expect(this.page.locator("a.expand-link.icon-up-arrow")).toBeVisible();
    }

    /**
     * Ticks an assessment checkbox
     * @param assessmentTitle - The assessment title to tick
     * @param checkboxNumber - The checkbox number to tick
     */
    async tickAssessmentCheckbox(assessmentTitle: string, checkboxNumber: number): Promise<void> {
        // await this.page.locator(`label[for=chk-assessment-${checkboxNumber}]`).click();
        await this.page
            .getByRole("list")
            .filter({ hasText: `${assessmentTitle}` })
            .locator(`#chk-assessment-${checkboxNumber}`)
            .click();
    }

    /**
     * Clicks Link Activity button
     * @param unitNumber - The unit number to click
     */
    async clickLinkActivities(): Promise<void> {
        await this.page.locator(CreateLessonPlan.DATA_TEST_LINK_ACTIVITY_BUTTON).click();
    }
}
