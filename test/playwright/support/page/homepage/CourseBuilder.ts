import type { Page } from "@playwright/test";
import { expect } from "@schoolboxTest/playwright/support/integrations";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

export default class CourseBuilder {
    private tester: AcceptanceTester;

    private page: Page;

    private homepage: Homepage;

    static readonly DATA_TEST_COMPONENT_NAME = "homepage-component-titlebar-name";

    static readonly DATA_TEST_COMPONENT_CONTAINER = "course-builder-component-container";

    static readonly DATA_TEST_CREATE_NEW_ACTIVITY = "course-builder-create-new-activity";

    static readonly DATA_TEST_CREATE_ACTIVITY_BUTTON = "create-activity-{activityName}";

    static readonly DATA_TEST_ACTIVITY_MENU_BUTTON = "sbx-pop-out-dropdown-{activityId}-button";

    static readonly DATA_TEST_DELETE_ACTIVITY_BUTTON = "builder-delete-activity-{activityId}";

    static readonly DATA_TEST_MODIFY_BUTTON = "assessment-modify-{activityId}";

    static readonly DATA_TEST_EXPORT_BUTTON = "builder-export-activity-{activityId}";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
        this.homepage = new Homepage(tester, page);
    }

    /**
     * click the create activity button in the course builder component
     * @param unitSpecificationId - the id of the unit specification to create the activity under
     */
    async clickCreateActivityButton(unitSpecificationId: number): Promise<void> {
        const component = this.homepage.locateComponentByName("Course Builder");
        await component.getByTestId(CourseBuilder.DATA_TEST_CREATE_NEW_ACTIVITY).click();
        expect(this.page.url()).toContain(`/learning/unitspecs/${unitSpecificationId}/assessments/new`);
    }

    /**
     * click the activity button in the create activity page in the choose activity type step
     * @param activityName - the name of the activity to click. Can be: project, task, quiz, due-work, lesson-plan, lti
     */
    async clickActivityButton(activityName: string): Promise<void> {
        await this.page
            .getByTestId(CourseBuilder.DATA_TEST_CREATE_ACTIVITY_BUTTON.replace("{activityName}", activityName))
            .click();
    }

    /**
     * clicks the activity 3-dot menu by passing the activity id
     * check the options are properly presented for the activity type
     * @param activityId - the id of the activity to click the menu for
     * @param activityType - the type of the activity to check the options for. Can be: project, task, quiz, due-work, lesson-plan
     */
    async clickActivityMenuAndCheckOptions(activityId: number, activityType: string): Promise<void> {
        await this.page
            .getByTestId(CourseBuilder.DATA_TEST_ACTIVITY_MENU_BUTTON.replace("{activityId}", activityId.toString()))
            .click();
        await expect(this.page.getByTestId(`builder-export-activity-${activityId}`)).toBeVisible();
        await expect(
            this.page.getByTestId(
                CourseBuilder.DATA_TEST_DELETE_ACTIVITY_BUTTON.replace("{activityId}", activityId.toString()),
            ),
        ).toBeVisible();
        if (activityType === "quiz") {
            await expect(this.page.getByTestId(`assessment-modify-${activityId}`)).toBeVisible();
            await expect(this.page.getByTestId(`assessment-modify-contents-${activityId}`)).toBeVisible();
        } else {
            await expect(this.page.getByTestId(`assessment-modify-${activityId}`)).toBeVisible();
        }
        await this.page
            .getByTestId(CourseBuilder.DATA_TEST_ACTIVITY_MENU_BUTTON.replace("{activityId}", activityId.toString()))
            .click();
    }

    /**
     * locate the course builder component and check the breadcrumb is properly presented with the course name
     * @param courseNames - the name(s) of the course to be shown in the breadcrumb
     * @param homepageIds - the id(s) of the homepage to be linked in the breadcrumb, in the same order as courseNames
     */
    async checkBreadcrumb(courseNames: string | string[], homepageIds: number | number[]): Promise<void> {
        const component = this.homepage.locateComponentByName("Course Builder");
        const breadcrumb = component.locator("ul.breadcrumb");

        const names = Array.isArray(courseNames) ? courseNames : [courseNames];
        const homepageIdsArray = Array.isArray(homepageIds) ? homepageIds : [homepageIds];

        await Promise.all(
            names.map(async (name, i) => {
                const homepageId = homepageIdsArray[i];
                await expect(breadcrumb.locator(`a:text-is("${name}")`)).toHaveAttribute(
                    "href",
                    `/homepage/${homepageId}/`,
                );
            }),
        );
    }

    /**
     * Get the current order of activities
     * @returns - The order of activity IDs
     */
    async getActivityOrder(): Promise<string[]> {
        const allActivityRowSelector = /^course-builder-assessment-title-/;
        const pageOrder = await this.page
            .getByTestId(allActivityRowSelector)
            .evaluateAll((activityRows) =>
                activityRows.map((activityRow) => activityRow.getAttribute("data-test-activity-id")),
            );
        return pageOrder.filter((id) => id !== null);
    }

    /**
     * drag and drop an activity to a new position
     * @param activityIdToDrag - The ID of the activity to drag to
     * @param activityIdToDragFrom - The ID of the activity to drag from
     */
    async dragAndDropActivity(activityIdToDrag: number, activityIdToDragFrom: number): Promise<void> {
        const activityRow = this.page.locator(`li[data-assessment="${activityIdToDrag}"]`);

        const dragHandle = activityRow.locator("div.grip.compact.ui-sortable-handle");
        await dragHandle.scrollIntoViewIfNeeded();
        await dragHandle.hover();
        await this.page.mouse.down();

        const courseBuilderComponent = this.page.locator(`div[class="course-builder"]`);

        // Move mouse to top of container, then wait for the reorder to happen
        await courseBuilderComponent.hover({ position: { x: 0, y: 1 } });

        this.tester.isGoingTo("keep dragging and wait for the units to reorder");
        let newActivityOrder = await this.getActivityOrder();
        const startTime = Date.now();
        while (
            newActivityOrder.indexOf(activityIdToDrag.toString()) >
                newActivityOrder.indexOf(activityIdToDragFrom.toString()) &&
            Date.now() - startTime < 5000 // Timeout after 5 seconds
        ) {
            newActivityOrder = await this.getActivityOrder();
        }

        await this.page.mouse.up();
    }

    /**
     * Delete an activity by clicking the delete button in the activity 3-dot menu and confirming it
     * @param activityId - the id of the activity to delete
     */
    async deleteActivity(activityId: number): Promise<void> {
        await this.page
            .getByTestId(CourseBuilder.DATA_TEST_ACTIVITY_MENU_BUTTON.replace("{activityId}", activityId.toString()))
            .click();
        await this.page
            .getByTestId(CourseBuilder.DATA_TEST_DELETE_ACTIVITY_BUTTON.replace("{activityId}", activityId.toString()))
            .click();
        await this.page.getByTestId("confirm-dialog-confirm-button").click();
    }

    /**
     * Click export in the 3-dot menu of an activity
     * @param activityId - the id of the activity to export
     */
    async exportActivity(activityId: number): Promise<void> {
        await this.page
            .getByTestId(CourseBuilder.DATA_TEST_ACTIVITY_MENU_BUTTON.replace("{activityId}", activityId.toString()))
            .click();
        await this.page
            .getByTestId(CourseBuilder.DATA_TEST_EXPORT_BUTTON.replace("{activityId}", activityId.toString()))
            .click();
    }

    /**
     * Click modify in the 3-dot menu of an activity
     * @param activityId - the id of the activity to modify
     */
    async modifyActivity(activityId: number): Promise<void> {
        await this.page
            .getByTestId(CourseBuilder.DATA_TEST_ACTIVITY_MENU_BUTTON.replace("{activityId}", activityId.toString()))
            .click();
        await this.page
            .getByTestId(CourseBuilder.DATA_TEST_MODIFY_BUTTON.replace("{activityId}", activityId.toString()))
            .click();
    }
}
