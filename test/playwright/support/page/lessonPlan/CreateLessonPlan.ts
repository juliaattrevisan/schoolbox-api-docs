import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";

export default class CreateLessonPlan {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly CREATE_BUTTON = "input[type=submit][value=Create]";

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
        await this.page.click(CreateLessonPlan.CREATE_BUTTON);
    }
}
