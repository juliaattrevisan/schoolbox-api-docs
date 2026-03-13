import type { Locator, Page } from "@playwright/test";
import { expect } from "@schoolboxTest/playwright/support/integrations";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { ActivityType } from "@schoolboxAssessment/Types/assessmentTypes";
import Homepage from "./Homepage";

export default class CourseOutline {
    private tester: AcceptanceTester;

    private page: Page;

    private homepage: Homepage;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;

        this.homepage = new Homepage(tester, page);
    }

    /**
     * clicks a filter label in the course outline component
     * @param componentId - The ID of the course outline component eg: id="component4902"
     * @param label - The label to click dueWork, task, project, quiz, lessonPlan
     */
    async filterByLabel(componentId: string, label: string): Promise<void> {
        this.tester.isGoingTo(`click the filter label: ${label}`);
        const courseOutline = this.homepage.locateComponentByName("Course Outline");
        await courseOutline.locator(`label[for="${componentId}-assessmentTypes-${label}"]`).click();
    }

    /**
     * clicks the unit 3-dot menu in the course outline component
     * @param unitName - The name of the unit to find in the course outline
     */
    async clickUnitDotMenu(unitName: string): Promise<void> {
        // Find the course outline and the unit we created inside it
        this.tester.isGoingTo("find the unit in the course outline");
        const courseOutline = this.homepage.locateComponentByName("Course Outline");
        const courseOutlineId = await courseOutline.getAttribute("id");
        const courseOutlineUnit = courseOutline.locator("li[data-unit]").filter({ hasText: unitName });

        this.tester.isGoingTo("click to open the 3-dot menu of the unit");
        await courseOutlineUnit
            .getByTestId(
                `sbx-pop-out-${courseOutlineId}-unit-options${await courseOutlineUnit.getAttribute("data-unit")}-button`,
            )
            .click();
    }

    /**
     * Uses the course outline component to find an unit and clicks its 3-dot menu
     * clicks one of the options in the menu: project, task, due work, quiz, lesson plan
     * @param unitName - The name of the unit to find in the course outline
     * @param menuOption - The assessment type to add
     */
    async selectOptionUnitDotMenu(unitName: string, menuOption: ActivityType): Promise<void> {
        // Find the course outline and the unit we created inside it
        this.tester.isGoingTo("find the unit in the course outline");
        const courseOutline = this.homepage.locateComponentByName("Course Outline");
        const courseOutlineId = await courseOutline.getAttribute("id");
        const courseOutlineUnit = courseOutline.locator("li[data-unit]").filter({ hasText: unitName });
        const courseOutlineUnitId = await courseOutlineUnit.getAttribute("data-unit");

        this.tester.isGoingTo("click to open the 3-dot menu of the unit");
        await courseOutlineUnit
            .getByTestId(`sbx-pop-out-${courseOutlineId}-unit-options${courseOutlineUnitId}-button`)
            .click();

        this.tester.isGoingTo("click one of the dropdown menu options");
        await courseOutlineUnit.getByTestId(`course-outline-menu-add-${menuOption}`).click();
    }

    /**
     * Uses the course outline component to find an unit and click the expand/collapse arrow
     * @param unitName - The name of the unit to find in the course outline
     * @param arrowOption - The option to select when expanding/collapsing the unit: expanded, collapsed
     */
    async clickUnitExpandCollapse(unitName: string, arrowOption: "expanded" | "collapsed"): Promise<void> {
        // Find the course outline and the unit we created inside it
        this.tester.isGoingTo("find the unit in the course outline");
        const courseOutline = this.homepage.locateComponentByName("Course Outline");
        const courseOutlineUnit = courseOutline.locator("li[data-unit]").filter({ hasText: unitName });

        this.tester.isGoingTo("click to expand/collapse the unit");
        await courseOutlineUnit.getByTestId(`course-outline-expand-unit-${unitName}-${arrowOption}`).click();
    }

    /**
     * Uses the course outline component to find an activity under a unit and click the expand/collapse arrow to view custom activities
     * @param unitName - The name of the unit to find in the course outline
     * @param activityName - The name of the activity to find in the unit
     */
    async clickActivityExpandCollapse(unitName: string, activityName: string): Promise<void> {
        // Find the course outline and the unit we created inside it
        this.tester.isGoingTo("find the unit in the course outline");
        const courseOutline = this.homepage.locateComponentByName("Course Outline");
        const courseOutlineUnit = courseOutline.locator("li[data-unit]").filter({ hasText: unitName });
        const activities = courseOutlineUnit.locator("li[data-assessment]");
        const activity = activities.filter({ hasText: activityName });

        this.tester.isGoingTo("click to expand/collapse the activity to view custom activities");
        await activity.getByTestId(`course-outline-expand-activity-${activityName}`).click();
    }

    /**
     * Uses the course outline component to find an activity under a unit
     * check the activity has customised activities under it with the correct title
     * @param unitName - The name of the unit to find in the course outline
     * @param activityName - The name of the activity to find in the unit
     * @param customActivityTitle - The title of the custom activity to check is shown under the activity
     */
    async checkCustomActivityPresented(
        unitName: string,
        activityName: string,
        customActivityTitle: string,
    ): Promise<void> {
        // Find the course outline and the unit we created inside it
        this.tester.isGoingTo("find the unit in the course outline");
        const courseOutline = this.homepage.locateComponentByName("Course Outline");
        const courseOutlineUnit = courseOutline.locator("li[data-unit]").filter({ hasText: unitName });
        const activities = courseOutlineUnit.locator("li[data-assessment]");
        const activity = activities.filter({ hasText: activityName });

        this.tester.isGoingTo("check the custom activity is shown in the course outline");
        await expect(activity.locator(`li.child:has-text("${customActivityTitle}")`)).toBeVisible();
    }

    /**
     * finds the activity id by passing its name
     * @param courseOutlineHeader - The header of the course outline component
     * @param unitName - The name of the unit to find in the course outline
     * @param activityName - The title of the activity to find in the unit
     * @returns the activity id as a string
     */
    public async getActivityId(courseOutlineHeader: string, unitName: string, activityName: string): Promise<number> {
        // Find the course outline and the unit we created inside it
        const courseOutline = this.homepage.locateComponentByName(courseOutlineHeader);
        const courseOutlineUnit = courseOutline.locator("li[data-unit]").filter({ hasText: unitName });
        // Find the activities under the unit using the activity title
        const activities = courseOutlineUnit.locator("li[data-assessment]");
        const activity = activities.filter({ hasText: activityName });
        const activityId = await activity.getAttribute("data-assessment");
        expect(activityId).not.toBeNull();
        if (activityId === null) throw new Error("activityId is null");
        return parseInt(activityId, 10);
    }

    /**
     * Uses the course outline component to find an unit and view the activity 3-dot menu
     * @param courseOutlineHeader - The header of the course outline component
     * @param unitName - The name of the unit to find in the course outline
     * @param activityName - The title of the activity to find in the unit
     */
    async clickActivityDotMenu(courseOutlineHeader: string, unitName: string, activityName: string): Promise<void> {
        // Find the course outline and the unit we created inside it
        const courseOutline = this.homepage.locateComponentByName(courseOutlineHeader);
        const courseOutlineUnit = courseOutline.locator("li[data-unit]").filter({ hasText: unitName });
        // Find the activities under the unit using the activity title
        const activities = courseOutlineUnit.locator("li[data-assessment]");
        const activity = activities.filter({ hasText: activityName });
        // gets the activity id
        const activityId = await activity.getAttribute("data-assessment");
        const courseOutlineId = await courseOutline.getAttribute("id");

        this.tester.isGoingTo("click to open the 3-dot menu of the activity");
        await courseOutlineUnit.getByTestId(`sbx-pop-out-${courseOutlineId}-options-task${activityId}-button`).click();
    }

    /**
     * Uses the course outline component to find an unit and click the activity 3-dot menu
     * @param unitName - The name of the unit to find in the course outline
     * @param activityId - The id of the activity to find in the unit
     * @param actionActivityId - The action and activity id to click in the menu - eg save-course-664, delete-664
     */
    async selectOptionActivityDotMenu(
        unitName: string,
        activityId: number | string,
        actionActivityId: string,
    ): Promise<void> {
        // Find the course outline and the unit we created inside it
        this.tester.isGoingTo("find the unit in the course outline");
        const courseOutline = this.homepage.locateComponentByName("Course Outline");
        const courseOutlineId = await courseOutline.getAttribute("id");
        const courseOutlineUnit = courseOutline.locator("li[data-unit]").filter({ hasText: unitName });

        this.tester.isGoingTo("click to open the 3-dot menu of the activity");
        await courseOutlineUnit.getByTestId(`sbx-pop-out-${courseOutlineId}-options-task${activityId}-button`).click();

        this.tester.isGoingTo("click one of the dropdown menu options");
        await courseOutlineUnit.getByTestId(`activity-${actionActivityId}`).click();
    }

    /**
     * Uses the course outline component to view the status of an activity
     * @param unitId - The ID of the unit to find
     * @param activityName - The name of the activity to find
     */
    async viewUnitActivityStatus(unitId: string | number, activityName: string): Promise<void> {
        // Find the course outline and the unit we created inside it
        this.tester.isGoingTo("find the unit in the course outline");
        const courseOutline = this.homepage.locateComponentByName("Course Outline");
        const courseOutlineUnit = courseOutline.locator(`li[data-unit="${unitId}"]`);

        this.tester.isGoingTo("click to view the status of the activity");
        const activities = courseOutlineUnit.locator("li[data-assessment]");
        const activity = activities.filter({ hasText: activityName });
        const activityId = await activity.getAttribute("data-assessment");
        const courseOutlineId = await courseOutline.getAttribute("id");
        await activity.getByTestId(`sbx-pop-out-${courseOutlineId}-options-task${activityId}-button`).click();

        await activity.locator("[data-dropdown-content] a.icon-progress").click();
    }

    /**
     * Uses the course outline component to find an activity and go to the summary page for it
     * @param unitId - The ID of the unit to find
     * @param activityName - The name of the activity to find
     */
    async viewUnitActivitySummary(unitId: string | number, activityName: string): Promise<void> {
        // Find the course outline and the unit we created inside it
        this.tester.isGoingTo("find the unit in the course outline");
        const courseOutline = this.homepage.locateComponentByName("Course Outline");
        const courseOutlineUnit = courseOutline.locator(`li[data-unit="${unitId}"]`);

        this.tester.isGoingTo("click to view the summary of the activity");
        const activities = courseOutlineUnit.locator("li[data-assessment]");
        const activity = activities.filter({ hasText: activityName });
        await activity.locator("a").first().click();
    }

    /**
     * licks the save to course button in the activity 3-dot menu
     * confirms it in the confirm dialog
     * @param unitName - The name of the unit to find in the course outline
     * @param activityId - The id of the activity to find in the unit
     * @param actionActivityId - The action and activity id to click in the menu - eg save-course-664
     */
    async saveActivityToCourse(unitName: string, activityId: number | string, actionActivityId: string): Promise<void> {
        this.tester.isGoingTo("click the save to course button in the modal");
        await this.selectOptionActivityDotMenu(unitName, activityId, actionActivityId);

        this.tester.isGoingTo("confirm in the confirm dialog");

        await this.page.getByTestId(`confirm-dialog-yes-button${activityId}`).getByText("Yes").click();
    }

    /**
     * clicks the delete buttons in the activity 3-dot menu
     * confirms it in the confirm dialog
     * @param unitName - The name of the unit to find in the course outline
     * @param activityId - The id of the activity to find in the unit
     * @param actionActivityId - The action and activity id to click in the menu - eg delete-664
     */
    async deleteActivity(unitName: string, activityId: number | string, actionActivityId: string): Promise<void> {
        this.tester.isGoingTo("click the save to course button in the modal");
        await this.selectOptionActivityDotMenu(unitName, activityId, actionActivityId);

        this.tester.isGoingTo("confirm in the confirm dialog");
        await this.page.getByTestId("confirm-dialog-confirm-button").getByText("Yes, permanently delete").click();
        await this.page.getByTestId("confirm-dialog-confirm-button").isHidden();
        await expect(this.page.getByTestId("toast")).toBeHidden();
    }

    /**
     * select an imported course from the course outline component dropdown
     * @param component - The locator of the course outline component
     * @param courseName - The name of the course to select
     */
    static async selectImportedCourse(component: Locator, courseName: string): Promise<void> {
        await component.locator('select[name="importedCourse"]').selectOption(courseName);
    }

    /**
     * click the check box "Show Title to Read-Only Users"
     * @param component - The locator of the course outline component
     * @param componentId - The ID of the course outline component eg: id="component4902"
     */
    static async clickReadOnlyUsers(component: Locator, componentId: string): Promise<void> {
        await component.locator(`label[for="${componentId}-showComponentTitle"]`).isVisible();
        await component.locator(`label[for="${componentId}-showComponentTitle"]`).click();
    }

    /**
     * given a list of activities name check whether the proper meta text is presented for the activity or not
     * pass false to check the meta text is not presented and true is by default to check the meta text is presented
     * @param activities - The list of activities with the meta text to check eg: [{activityName: "Persuasive Article", expectMetaText: "0/5 students completed", isMetaTextPresented: true}]
     * isMetaTextPresented property can be true or false depending on whether you want to check the meta text is presented or not
     */
    async checkActivityMetaText(
        activities: { activityName: string; expectMetaText: string; isMetaTextPresented?: boolean }[],
    ): Promise<void> {
        for (const activity of activities) {
            const metaTextLocator = this.page.getByTestId(`course-outline-number-students-${activity.activityName}`);
            if (activity.isMetaTextPresented === false) {
                await expect(metaTextLocator).not.toContainText(activity.expectMetaText);
            } else {
                await expect(metaTextLocator).toContainText(activity.expectMetaText);
            }
        }
    }
}
