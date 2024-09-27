import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { Page } from "@playwright/test";

export default class TemplateList {
    private tester: AcceptanceTester;

    private page: Page;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * click the template link
     * @param lessonPlanTemplateId - the id of the lesson plan template
     */
    async clickTemplateLink(lessonPlanTemplateId: number): Promise<void> {
        await this.page.click(`a[data-reveal-id="lpt-preview-${lessonPlanTemplateId}"][title="Preview this template"]`);
    }
}
