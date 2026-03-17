import { expect } from "@playwright/test";
import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class Homepage {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/homepage/{id}";

    static readonly ELEMENT_ADD_COMPONENT_TAB = "#tabs-1";

    static readonly ELEMENT_ADD_COMPONENT_SECTION_INFORMATION = "#tabs-sub-1";

    static readonly ELEMENT_ADD_COMPONENT_SECTION_COLLABORATION = "#tabs-sub-2";

    static readonly ELEMENT_ADD_COMPONENT_SECTION_LEARNING = "#tabs-sub-3";

    static readonly ELEMENT_ADD_COMPONENT_SECTION_MULTIMEDIA = "#tabs-sub-4";

    static readonly ELEMENT_ADD_COMPONENT_SECTION_RESOURCES = "#tabs-sub-5";

    static readonly ELEMENT_ADD_COMPONENT_SECTION_FEEDS = "#tabs-sub-6";

    static readonly ELEMENT_COMPONENT_SECTION_INFORMATION = 'a[href="#tabs-sub-1"]';

    static readonly ELEMENT_COMPONENT_SECTION_COLLABORATION = 'a[href="#tabs-sub-2"]';

    static readonly ELEMENT_COMPONENT_SECTION_LEARNING = 'a[href="#tabs-sub-3"]';

    static readonly ELEMENT_COMPONENT_SECTION_MULTIMEDIA = 'a[href="#tabs-sub-4"]';

    static readonly ELEMENT_COMPONENT_SECTION_RESOURCES = 'a[href="#tabs-sub-5"]';

    static readonly ELEMENT_COMPONENT_SECTION_FEEDS = 'a[href="#tabs-sub-6"]';

    static readonly ELEMENT_EDIT_COLUMNS_TAB = "#tabs-2";

    static readonly DATA_TEST_LINK_MENU_BUTTON = "sbx-pop-out-tab-control-button";

    static readonly ELEMENT_COMPONENT_LAYOUT = "#component-layout";

    static readonly ELEMENT_COMPONENT_LAYOUT_TOP = `${Homepage.ELEMENT_COMPONENT_LAYOUT} div.column-top`;

    static readonly ELEMENT_COMPONENT_LAYOUT_LEFT = `${Homepage.ELEMENT_COMPONENT_LAYOUT} div.column-left`;

    static readonly ELEMENT_COMPONENT_LAYOUT_RIGHT = `${Homepage.ELEMENT_COMPONENT_LAYOUT} div.column-right`;

    static readonly ELEMENT_COMPONENT = "div.component-container";

    static readonly ELEMENT_COMPONENT_HEADING = ".component-titlebar h2";

    static readonly ELEMENT_COMPONENT_CONTENT = ".content";

    static readonly ELEMENT_COMPONENT_ACTION_BAR = ".component-action";

    static readonly ELEMENT_LOCK_TOGGLE = "#component-lock-switch";

    static readonly ELEMENT_COMPONENT_SETTINGS = "a.icon-settings.settings.edit";

    static readonly ELEMENT_REMOVE_ICON = "a.icon-close";

    static readonly ELEMENT_COMPONENT_EDIT_HEADING = 'input[name="componentname"]';

    static readonly REMOVE_BUTTON_CONFIRMATION_MESSAGE = "Remove";

    static readonly DATA_TEST_ASSESSMENT_COMPONENT_ROW = "component-assessment-"; // append "{componentId}"

    static readonly DATA_TEST_ASSESSMENT_COMPONENT_TOT_MENU = "sbx-pop-out-component-assessment-actions-"; // append "{assessmentId}"-button

    static readonly DATA_TEST_ASSESSMENT_COMPONENT_TITLE = "component-assessment-title-";

    static readonly DATA_TEST_ASSESSMENT_COMPONENT_TOT_MENU_VIEW_STATUS = "component-assessment-actions-viewStatus-"; // append "{assessmentId}"

    static readonly DATA_TEST_ASSESSMENT_COMPONENT_TOT_MENU_MODIFY_SETTINGS = "assessment-modify-"; // append "{assessmentId}"

    static readonly DATA_TEST_ASSESSMENT_COMPONENT_TOT_MENU_MODIFY_CONTENTS = "assessment-modify-contents-"; // append "{assessmentId}"

    static readonly DATA_TEST_ASSESSMENT_COMPONENT_TOT_MENU_CONFIGURE_AGENT = "assessment-configure-agent-"; // append "{assessmentId}"

    static readonly DATA_TEST_ASSESSMENT_COMPONENT_TOT_MENU_DELETE = "component-assessment-actions-delete-";

    static readonly DATA_TEST_HOMEPAGE_COMPONENT_TITLEBAR_NAME = "homepage-component-titlebar-name";

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Go to a homepage for a given ID
     * @param id - The ID of the homepage
     */
    async goto(id: number): Promise<void> {
        await this.page.goto(Homepage.generateRoute(id));
    }

    /**
     * Generates route for this page
     * @param id - The ID of the page
     * @returns - the URL for the page
     */
    public static generateRoute(id: number): string {
        return Homepage.URL.replace("{id}", id.toString());
    }

    /**
     * Adds the given component
     * @param componentSection - The section it's in: one of the `self::ELEMENT_ADD_COMPONENT_SECTION_*` constants
     * @param componentName - The name of the component, as seen in its link
     */
    async addComponent(componentSection: string, componentName: string): Promise<void> {
        // Open the component tab
        await this.openTabPanel(Homepage.ELEMENT_ADD_COMPONENT_TAB);

        // Which component section is it in?
        await this.openTabPanel(componentSection);

        // Click to add the component
        const componentSectionEl = this.page.locator(componentSection);
        await expect(componentSectionEl.getByText(componentName)).toBeVisible();
        await componentSectionEl.getByText(componentName).click();

        // Wait for it to load
        // TODO: Check if needed
        // await this.tester.waitForAjaxDone();
    }

    /**
     * Create a locator for a component in a layout for a given position
     * @param layout - The layout in which the component lives: one of the `self::ELEMENT_COMPONENT_LAYOUT_*` constants
     * @param position - The position of the component (1-indexed)
     * @returns - the locator for the component
     */
    public static locateComponent(layout: string, position: number): string {
        return `${layout} ${Homepage.ELEMENT_COMPONENT}:nth-child(${position})`;
    }

    /**
     * Create a locator for a component by its name
     * @param componentName - The name of the component
     * @returns - the Locator for the component (or components)
     */
    public locateComponentByName(componentName: string): Locator {
        const titleBar = this.page
            .getByTestId(Homepage.DATA_TEST_HOMEPAGE_COMPONENT_TITLEBAR_NAME)
            .filter({ hasText: componentName });

        const components = this.page.locator("[data-component]");
        return components.filter({ has: titleBar });
    }

    /**
     * Verify that the component at the given position has the given heading
     * @param heading - The heading to verify
     * @param layout - The layout of the component
     * @param position - The position of the component (1-indexed)
     */
    async seeInComponentHeading(heading: string, layout: string, position: number): Promise<void> {
        const componentLocator = Homepage.locateComponent(layout, position);
        const componentHeadingEl = this.page.locator(`${componentLocator} ${Homepage.ELEMENT_COMPONENT_HEADING}`);
        await expect(componentHeadingEl).toHaveText(heading);
    }

    /**
     * Verify that the component at the given position contains the given content
     * @param content - The content to verify
     * @param layout - The layout of the component
     * @param position - The position of the component (1-indexed)
     */
    async seeInComponentContent(content: string, layout: string, position: number): Promise<void> {
        const componentLocator = Homepage.locateComponent(layout, position);
        const componentContentEl = this.page.locator(`${componentLocator} ${Homepage.ELEMENT_COMPONENT_CONTENT}`);
        await expect(componentContentEl).toHaveText(content);
    }

    /**
     * Clicks a component empty state link
     * @param layout - The layout of the component
     * @param position - The position of the component (1-indexed)
     */
    async clickComponentEmptyStateLink(layout: string, position: number): Promise<void> {
        const componentLocator = Homepage.locateComponent(layout, position);
        await this.page.locator(`${componentLocator} .empty-state a`).click();
    }

    /**
     * Clicks a component action bar button
     * @param button - The button to click (as a queryselector)
     * @param layout - The layout of the component
     * @param position - The position of the component (1-indexed)
     */
    async clickComponentActionBarButton(button: string, layout: string, position: number): Promise<void> {
        await this.componentActionBarButton(button, layout, position).click();
    }

    /**
     * Returns a component action bar button
     * @param button - The button to click (as a queryselector)
     * @param layout - The layout of the component
     * @param position - The position of the component (1-indexed)
     * @returns - Locator of the button
     */
    componentActionBarButton(button: string, layout: string, position: number): Locator {
        const componentLocator = Homepage.locateComponent(layout, position);
        return this.page.locator(`${componentLocator} ${Homepage.ELEMENT_COMPONENT_ACTION_BAR}`).locator(button);
    }

    /**
     * Open a tab panel
     * @param link - The link of the tab panel
     */
    async openTabPanel(link: string): Promise<void> {
        await this.page.locator(`[href="${link}"]`).click();
    }

    /**
     * Click a tab to add components
     * @param tab - The tab to click
     */
    async clickComponentsTab(tab: string): Promise<void> {
        await this.page.locator(tab).click();
    }

    /**
     * Check component icons
     * @param icon - The icon to check
     */
    async checkComponentIcon(icon: string): Promise<void> {
        await expect(this.page.locator(`span.size-32.icon-masonry.icon-masonry-${icon}`)).toBeVisible();
    }

    /**
     * Click the lock toggle
     */
    async clickLockToggle(): Promise<void> {
        await this.page.click(Homepage.ELEMENT_LOCK_TOGGLE);
    }

    /**
     * @param component - The locator of the component to check
     */
    public static async clickComponentSettings(component: Locator): Promise<void> {
        await component.locator(Homepage.ELEMENT_COMPONENT_SETTINGS).click();
    }

    /**
     * Click the component remove icon
     */
    async clickComponentRemoveIcon(): Promise<void> {
        await this.page.locator(Homepage.ELEMENT_REMOVE_ICON).first().click();
    }

    /**
     * Type a heading text into a component's heading field
     * @param headingText - The text to type into the heading field.
     */
    async typeTextBoxHeading(headingText: string): Promise<void> {
        await this.page.waitForSelector(Homepage.ELEMENT_COMPONENT_EDIT_HEADING);
        await this.page.fill(Homepage.ELEMENT_COMPONENT_EDIT_HEADING, headingText);
    }

    /**
     * Uncheck a component's checkbox
     * @param id - The component ID.
     * @param componentElement - The checkbox element name.
     */
    async uncheckComponentCheckbox(id: number, componentElement: string): Promise<void> {
        await this.page.waitForSelector(`label[for="component${id}-${componentElement}"]`);
        await this.page.locator(`label[for="component${id}-${componentElement}"]`).click();
    }

    /**
     * Check a component's checkbox
     * @param id - The component ID.
     * @param componentElement - The checkbox element name.
     */
    async checkComponentCheckbox(id: number, componentElement: string): Promise<void> {
        await this.page.check(`#component${id}-${componentElement}`);
    }

    /**
     * Click the remove confirmation button
     */
    async clickRemoveConfirmationMessage(): Promise<void> {
        await this.page.locator(`a:text("${Homepage.REMOVE_BUTTON_CONFIRMATION_MESSAGE}")`).first().click();
    }

    /**
     * Locate a particular assessment in the component by its name
     * @param component - Locator to the component in which to search for the assessment
     * @param assessmentName - the name of the assessment to locate
     * @returns Locator for the assessment with the given name
     */
    public locateAssessmentRow(component: Locator, assessmentName: string): Locator {
        const allAssessmentsSelector = new RegExp(`^${Homepage.DATA_TEST_ASSESSMENT_COMPONENT_ROW}`);
        const allTitlesSelector = new RegExp(`^${Homepage.DATA_TEST_ASSESSMENT_COMPONENT_TITLE}`);

        const matchingTitles = this.page.getByTestId(allTitlesSelector).filter({ hasText: assessmentName });
        return component.getByTestId(allAssessmentsSelector).filter({ has: matchingTitles });
    }

    /**
     * Retrieve the ID of an assessment row
     * @param assessmentRow - Locator for the relevant assessment row
     * @returns the assessment ID
     */
    public static async getAssessmentId(assessmentRow: Locator): Promise<number> {
        const assessmentId = await assessmentRow.getAttribute("data-assessment");
        // make sure it's a number
        expect(assessmentId).toMatch(/^\d+$/);
        return parseInt(assessmentId!, 10);
    }

    /**
     * Click the assessment component TOT menu
     * @param assessmentId - The assessment ID.
     */
    async clickAssessmentComponentTOTMenu(assessmentId: number): Promise<void> {
        await this.page
            .getByTestId(`${Homepage.DATA_TEST_ASSESSMENT_COMPONENT_TOT_MENU}${assessmentId}-button`)
            .click();
    }

    /**
     * Click the assessment component title
     * @param assessmentId - The assessment ID.
     */
    async clickAssessmentComponentTitle(assessmentId: number): Promise<void> {
        await this.page.getByTestId(`${Homepage.DATA_TEST_ASSESSMENT_COMPONENT_TITLE}${assessmentId}`).click();
    }

    /**
     * click "View Status" for an assessment in an assessment component
     * @param assessmentId - The assessment ID.
     */
    async clickAssessmentComponentTOTMenuViewStatus(assessmentId: number): Promise<void> {
        const viewStatusTestId = `${Homepage.DATA_TEST_ASSESSMENT_COMPONENT_TOT_MENU_VIEW_STATUS}${assessmentId}`;
        await this.page.getByTestId(viewStatusTestId).click();
    }

    /**
     * click "Modify" / "Modify Settings" for an assessment in an assessment component
     * @param assessmentId - The assessment ID.
     */
    async clickAssessmentComponentTOTMenuModifySettings(assessmentId: number): Promise<void> {
        const modifyButtonTestId = `${Homepage.DATA_TEST_ASSESSMENT_COMPONENT_TOT_MENU_MODIFY_SETTINGS}${assessmentId}`;
        await this.page.getByTestId(modifyButtonTestId).click();
    }

    /**
     * click "Modify Contents" for an assessment in an assessment component
     * This is specifically for Quizzes
     * @param assessmentId - The assessment ID.
     */
    async clickAssessmentComponentTOTMenuModifyContents(assessmentId: number): Promise<void> {
        const modifyContentsButtonTestId = `${Homepage.DATA_TEST_ASSESSMENT_COMPONENT_TOT_MENU_MODIFY_CONTENTS}${assessmentId}`;
        await this.page.getByTestId(modifyContentsButtonTestId).click();
    }

    /**
     * click "Configure Agent" for an assessment in an assessment component
     * This is specifically for Dialog Agents
     * @param assessmentId - The assessment ID.
     */
    async clickAssessmentComponentTOTMenuConfigureAgent(assessmentId: number): Promise<void> {
        const configureAgentButtonTestId = `${Homepage.DATA_TEST_ASSESSMENT_COMPONENT_TOT_MENU_CONFIGURE_AGENT}${assessmentId}`;
        await this.page.getByTestId(configureAgentButtonTestId).click();
    }

    /**
     * click Delete Settings via Quiz Component
     * @param assessmentId - The assessment ID.
     * .
     */
    async clickDeleteViaAssessmentComponent(assessmentId: number): Promise<void> {
        await this.page
            .getByTestId(`${Homepage.DATA_TEST_ASSESSMENT_COMPONENT_TOT_MENU_DELETE}${assessmentId}`)
            .click();
    }

    /**
     * Open the link menu for the homepage
     */
    async openLinkMenu(): Promise<void> {
        await this.page.getByTestId(Homepage.DATA_TEST_LINK_MENU_BUTTON).click();
    }

    /**
     * Get a Locator for a component in a layout region by its 1-based position.
     * @param layoutSelector - The layout region selector (e.g., Homepage.ELEMENT_COMPONENT_LAYOUT_TOP).
     * @param position - The 1-based index of the component within the region.
     * @returns Locator pointing to the requested component.
     */
    getComponent(layoutSelector: string, position: number): Locator {
        return this.page.locator(Homepage.locateComponent(layoutSelector, position));
    }
    /**
     * Get the component ID from the component locator
     * @param componentLocator : The Component locator
     * @returns The component ID
     */

    static async getComponentId(component: Locator): Promise<number> {
        const componentIdStr = await component.getAttribute("id");

        if (componentIdStr === null) {
            expect(componentIdStr).not.toBeNull();
            throw new Error("Component ID is null");
        }

        const componentId = Number(componentIdStr.replaceAll("component", ""));
        expect(Number.isFinite(componentId), `Bad id format: ${componentIdStr}`).toBeTruthy();

        return componentId;
    }
}
