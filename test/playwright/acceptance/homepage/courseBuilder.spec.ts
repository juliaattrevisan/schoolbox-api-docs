import type { Locator } from "@playwright/test";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CourseBuilder from "@schoolboxTest/playwright/support/page/homepage/CourseBuilder";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

/**
 * Tests for the Course Builder homepage component
 * Tests cover:
 * - empty message when there are no activities yet
 * - adding an activity and verifying it appears in the component
 * - checking the activities 3-dot menu options are presented for:
 *   - due work, task, quiz, project and lesson plans
 * - check the breadcrumb is properly presented in the component
 * - check it is possible to export an activity into a class page
 * - sort the activities and check it sorts correctly in the component and unit planner
 * - delete an activity and check it is removed from the component and unit planner
 * - check the student cannot see the course builder component
 */

test.describe.serial("Homepage - Course Builder Component", () => {
    let courseBuilder: CourseBuilder;
    let courseBuilderComponent: Locator;
    let courseBuilderComponentId: number;
    let homepage: Homepage;

    const COMPONENT_NAME = "Course Builder";

    test.beforeEach(async ({ tester, page }) => {
        courseBuilder = new CourseBuilder(tester, page);
        homepage = new Homepage(tester, page);

        await tester.loginAsAdmin();
    });

    test("Check the unit page course builder empty message and create an activity under it", async ({
        tester,
        page,
    }) => {
        tester.isGoingTo("navigate to homepage to check the course builder component empty message");
        await homepage.goto(145);
        tester.isGoingTo("check the empty message is shown in the course builder component");
        await homepage.verifyComponentEmptyStateIsShown(COMPONENT_NAME);
        tester.isGoingTo("click the create activity link and create a project");
        //  await courseBuilder.clickCreateActivityButton();
        await page.getByTestId("course-builder-create-new-activity").click();
        expect(page.url()).toContain(`/learning/unitspecs/31/assessments/new`);
    });
});
