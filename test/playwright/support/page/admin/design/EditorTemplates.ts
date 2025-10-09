import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";

export default class EditorTemplate {
    private page: Page;

    private tester: AcceptanceTester;

    // list page
    static readonly URL = "/adminv2/editor-templates";

    static readonly DATA_TEST_CREATE_NEW_TEMPLATE = "editor-templates-create-template-link-{template_type}";

    static readonly DATA_TEST_CREATE_BUTTON = "editor-templates-add-template-button-{template_type}";

    static readonly DATA_TEST_TEMPLATE_ITEM_LINK = "editor-templates-list-item-{template_title}";

    static readonly DATA_TEST_EDIT_BUTTON = "editor-templates-edit-icon-{template_title}";

    static readonly DATA_TEST_DELETE_BUTTON = "editor-templates-delete-icon-{template_title}";

    static readonly EMPTY_STATE = "#editor-templates-container";

    static readonly DATA_TEST_TAB = "editor-templates-tab-{template_type}";

    // create page
    static readonly URL_CREATE = "/adminv2/editor-templates/{category_id}/create";

    static readonly TEMPLATE_TITLE = "#template-title";

    static readonly TEMPLATE_DESCRIPTION = "#template-description";

    static readonly DATA_TEST_BUTTON_SAVE = "editor-templates-save";

    static readonly DATA_TEST_TABS = "editor-templates-tab-{template_type}";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * Navigates to the editor templates page "/adminv2/editor-templates"
     */
    async goto(): Promise<void> {
        await this.page.goto(EditorTemplate.URL);
    }

    /**
     * Navigates to the create templates page "/adminv2/editor-templates/{category_id}/create"
     * @param categoryId - The category ID: 1 for badge, 2 for calendar, 3 for email, 4 for feedback, 5 for homepage, 6 for activity, 7 for moments, 8 for news
     */
    async gotoCreateTemplatePage(categoryId: number): Promise<void> {
        await this.page.goto(EditorTemplate.URL_CREATE.replace("{category_id}", categoryId.toString()));
    }

    /**
     * clicks the link Create a new template
     * @param templateType - The template type: badge-evidence, calendar-events, email, feedback, homepage, learning-activity, learning-moments, news
     */
    async clickCreateNewTemplate(templateType: string): Promise<void> {
        this.tester.isGoingTo("click the link Create a new template");
        await this.page
            .getByTestId(EditorTemplate.DATA_TEST_CREATE_NEW_TEMPLATE.replace("{template_type}", templateType))
            .click();
    }

    /**
     * clicks the Add Template button
     * @param templateType - The template type: badge-evidence, calendar-events, email, feedback, homepage, learning-activity, learning-moments, news
     */
    async clickAddTemplateButton(templateType: string): Promise<void> {
        this.tester.isGoingTo("click the Add Template button");
        await this.page
            .getByTestId(EditorTemplate.DATA_TEST_CREATE_BUTTON.replace("{template_type}", templateType))
            .click();
    }

    /**
     * type a editor template title
     * @param templateTitle - the title of the template
     */
    async typeTitle(templateTitle: string): Promise<void> {
        await this.page.waitForSelector(EditorTemplate.TEMPLATE_TITLE);
        await this.page.fill(EditorTemplate.TEMPLATE_TITLE, templateTitle);
    }

    /**
     * clicks one of the templates tab
     * @param templateType - The template type: badge-evidence, calendar-events, email, feedback, homepage, learning-activity, learning-moments, news
     */
    async clickTemplateTab(templateType: string): Promise<void> {
        this.tester.isGoingTo("click the template tab");
        await this.page.getByTestId(EditorTemplate.DATA_TEST_TABS.replace("{template_type}", templateType)).click();
    }

    /**
     * type a description for the template
     * @param templateDescription - the description of the template
     */
    async typeDescription(templateDescription: string): Promise<void> {
        await this.page.waitForSelector(EditorTemplate.TEMPLATE_DESCRIPTION);
        await this.page.fill(EditorTemplate.TEMPLATE_DESCRIPTION, templateDescription);
    }

    /**
     * type a field body
     * @param body - the body text for the template
     */
    async fillBody(body: string): Promise<void> {
        await this.tester.fillCKEditorById(`template-body`, body);
    }

    /**
     * click Save button
     */
    async clickSave(): Promise<void> {
        this.tester.isGoingTo("click Save button");
        await this.page.getByTestId(EditorTemplate.DATA_TEST_BUTTON_SAVE).click();
    }

    /**
     * click a tab according to the template type
     * @param templateType - The template type: badge-evidence, calendar-events, email, feedback, homepage, learning-activity, learning-moments, news
     */
    async clickTab(templateType: string): Promise<void> {
        this.tester.isGoingTo(`click the ${templateType} tab`);
        await this.page.getByTestId(EditorTemplate.DATA_TEST_TABS.replace("{template_type}", templateType)).click();
    }

    /**
     * creates a template by passing its title, description and body
     * @param templateTitle - the title of the template
     * @param templateDescription - the description of the template
     * @param body - the body text for the template
     */
    async createTemplate(templateTitle: string, templateDescription: string, body: string): Promise<void> {
        this.tester.isGoingTo("Create a new template by filling the title, description and body and saving it");
        await this.typeTitle(templateTitle);
        await this.typeDescription(templateDescription);
        await this.fillBody(body);
        await this.clickSave();
    }

    /**
     * click the template title in the template list to preview it
     * @param templateTitle - the title of the template
     */
    async clickTemplateTitle(templateTitle: string): Promise<void> {
        this.tester.isGoingTo("click the template title in the template list");
        await this.page
            .getByTestId(EditorTemplate.DATA_TEST_TEMPLATE_ITEM_LINK.replace("{template_title}", templateTitle))
            .getByText(templateTitle)
            .click();
    }

    /**
     * click the edit icon in the template list to edit it
     * @param templateTitle - the title of the template
     */
    async clickEditIcon(templateTitle: string): Promise<void> {
        this.tester.isGoingTo("click the edit icon in the template list");
        await this.page
            .getByTestId(EditorTemplate.DATA_TEST_EDIT_BUTTON.replace("{template_title}", templateTitle))
            .click();
    }

    /**
     * click the delete icon in the template list to delete it
     * @param templateTitle - the title of the template
     */
    async clickDeleteIcon(templateTitle: string): Promise<void> {
        this.tester.isGoingTo("click the delete icon in the template list");
        await this.page
            .getByTestId(EditorTemplate.DATA_TEST_DELETE_BUTTON.replace("{template_title}", templateTitle))
            .click();
    }

    /**
     *clicks a few of the template type tabs to verify the proper empty message is shown according to the tab clicked
     */
    async verifyEmptyStateMessage(): Promise<void> {
        this.tester.isGoingTo("verify the empty state message for each template type tab");

        const templateTypes = [
            { tab: "calendar-events", message: "No Calendar Events templates have been created." },
            { tab: "badge-evidence", message: "No Badge Evidence templates have been created." },
            { tab: "email", message: "No Email templates have been created." },
            { tab: "feedback", message: "No Feedback templates have been created." },
            { tab: "homepage", message: "No Homepage templates have been created." },
            { tab: "learning-activity", message: "No Learning Activity templates have been created." },
            { tab: "learning-moments", message: "No Learning Moments templates have been created." },
            { tab: "news", message: "No News templates have been created." },
        ];

        // clicks each of the template tabs and verifies the empty state message as per above const
        for (const type of templateTypes) {
            await this.clickTab(type.tab);
            await expect(this.page.locator(EditorTemplate.EMPTY_STATE)).toContainText(type.message);
        }
    }

    /**
     * create a template and check it appears in the list
     * @param templateType - The template type: badge-evidence, calendar-events, email, feedback, homepage, learning-activity, learning-moments, news
     * @param templateTitle - the title of the template
     * @param templateDescription - the description of the template
     * @param body - the body text for the template
     */
    async createAndCheckTemplateAppearsInList(
        templateType: string,
        templateTitle: string,
        templateDescription: string,
        body: string,
    ): Promise<void> {
        this.tester.isGoingTo(
            `create a ${templateType} template and check it appears in the list of ${templateType} templates`,
        );
        await this.goto();
        await this.clickTemplateTab(templateType);
        await this.clickCreateNewTemplate(templateType);
        await this.createTemplate(templateTitle, templateDescription, body);
        await this.clickTab(templateType);
        await expect(
            this.page.getByTestId(
                EditorTemplate.DATA_TEST_TEMPLATE_ITEM_LINK.replace("{template_title}", templateTitle),
            ),
        ).toContainText(templateTitle);
        await expect(
            this.page.getByTestId(
                EditorTemplate.DATA_TEST_TEMPLATE_ITEM_LINK.replace("{template_title}", templateTitle),
            ),
        ).toContainText(templateDescription);
    }

    /**
     * creates a template for each type with unique title, description and body for each type and checks it appears in the list
     */
    async verifyCreateTemplates(): Promise<void> {
        const templateData = [
            {
                tab: "calendar-events",
                title: "Automation Template Event",
                description: "Description for this template",
                body: "Automation Body 1",
            },
            {
                tab: "badge-evidence",
                title: "Automation Template Badge",
                description: "Description for this template",
                body: "Automation Body 2",
            },
            {
                tab: "email",
                title: "Automation Template Email",
                description: "Description for this template",
                body: "Automation Body 3",
            },
            {
                tab: "feedback",
                title: "Automation Template Feedback",
                description: "Description for this template",
                body: "Automation Body 4",
            },
            {
                tab: "homepage",
                title: "Automation Template Homepage",
                description: "Description for this template",
                body: "Automation Body 5",
            },
            {
                tab: "learning-activity",
                title: "Automation Template Activity",
                description: "Description for this template",
                body: "Automation Body 6",
            },
            {
                tab: "learning-moments",
                title: "Automation Template Moments",
                description: "Description for this template",
                body: "Automation Body 7",
            },
            {
                tab: "news",
                title: "Automation Template News",
                description: "Description for this template",
                body: "Automation Body 8",
            },
        ];

        // clicks each of the template tabs and verifies the empty state message as per above const
        for (const data of templateData) {
            await this.createAndCheckTemplateAppearsInList(data.tab, data.title, data.description, data.body);
        }
    }
}
