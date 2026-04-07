import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";

export default class CreateLessonPlan {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly CREATE_BUTTON = "input[type=submit][value=Create]";

    static readonly LINK_ACTIVITY = "link existing activities";

    static readonly DATA_TEST_LINK_ACTIVITY_BUTTON = "lesson-plan-link-activities-link";

    static readonly LINK_ACTIVITY_BUTTON = 'a.button.expand:has(sbx-icon[name="link"])';

    static readonly URL_CREATE_LESSON_PLAN =
        "/learning/unitspecs/{unitSpecificationId}/assessments/new?lpt={lessonPlanTemplateId}&type=lessonPlan";

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
     * @param checkboxNumber - The checkbox number to tick
     */
    async tickAssessmentCheckbox(checkboxNumber: string): Promise<void> {
        // await this.page.locator(`label[for=chk-assessment-${checkboxNumber}]`).click();
        await this.page.locator(`label.checkbox-label[for=chk-assessment-${checkboxNumber}]`).click();
    }

    /**
     * Clicks Link Activity button
     */
    async clickLinkActivities(): Promise<void> {
        await this.page.locator(CreateLessonPlan.DATA_TEST_LINK_ACTIVITY_BUTTON).click();
    }

    /**
     * Clicks the link activity button on the modal
     */
    async clickLinkActivityButton(): Promise<void> {
        await this.page.locator(CreateLessonPlan.LINK_ACTIVITY_BUTTON).click();
    }

    /**
     * Go to create url to create a lesson plan under a unit by passing the unit specification id and the lesson plan template id
     * @param unitSpecificationId - The unit specification id to create the lesson plan under
     * @param lessonPlanTemplateId - The lesson plan template id to use for creating the lesson plan
     */
    async gotoCreateLessonPlanUrl(unitSpecificationId: number, lessonPlanTemplateId: number): Promise<void> {
        const url = CreateLessonPlan.URL_CREATE_LESSON_PLAN.replace(
            "{unitSpecificationId}",
            String(unitSpecificationId),
        ).replace("{lessonPlanTemplateId}", String(lessonPlanTemplateId));
        await this.page.goto(url);
    }

    /**
     * Creates a lesson plan by filling in the name and clicking the create button
     * @param unitSpecificationId - The unit specification id to create the lesson plan under
     * @param lessonPlanTemplateId - The lesson plan template id to use for creating the lesson plan
     * @param lessonPlanName - the name of the lesson plan to create
     */
    async createLessonPlan(
        unitSpecificationId: number,
        lessonPlanTemplateId: number,
        lessonPlanName: string,
    ): Promise<void> {
        await this.gotoCreateLessonPlanUrl(unitSpecificationId, lessonPlanTemplateId);
        await this.typeName(lessonPlanName);
        await this.clickCreateButton();
    }
}
