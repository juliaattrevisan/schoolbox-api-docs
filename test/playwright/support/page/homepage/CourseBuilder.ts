import type { Locator, Page } from "@playwright/test";
import { expect } from "@schoolboxTest/playwright/support/integrations";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

export default class CourseBuilder {
    private tester: AcceptanceTester;

    private page: Page;

    private homepage: Homepage;

    static readonly DATA_TEST_COMPONENT_NAME = "course-builder-component-name";

    static readonly DATA_TEST_COMPONENT_CONTAINER = "course-builder-component-container";

    static readonly DATA_TEST_CREATE_NEW_ACTIVITY = "course-builder-create-new-activity";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
        this.homepage = new Homepage(tester, page);
    }

    // click the create activity button in the course builder component
    async clickCreateActivityButton(): Promise<void> {
        const component = this.homepage.locateComponentByName(CourseBuilder.DATA_TEST_COMPONENT_NAME);
        await component.getByTestId(CourseBuilder.DATA_TEST_CREATE_NEW_ACTIVITY).click();
    }
}
