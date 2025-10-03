import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import EditorTemplates from "@schoolboxTest/playwright/support/page/admin/design/EditorTemplates";

/**
 * This test adds, updates and deletes a template
 * Applies to editor templates for each feature
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. npx playwright test design
 */

test.describe("Editor templates tests", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });
    /**
     * Preparations
     *
     * get logged in
     * navigate to  /adminv2/lesson-plan/template/create
     */
    test.beforeEach(async ({ tester, page }) => {
        await tester.logout();
        await tester.loginAsAdmin();
        const editorTemplates = new EditorTemplates(page, tester);
        await editorTemplates.goto();
    });

    /**
     * on /adminv2/editor-templates page click Add template button
     * Click each tab and checks its empty message
     * Attempt to save without entering a title and or body
     * Ensure validation error is shown
     * Type a Title, Description and Body
     * Click Save
     * Ensure the template is presented in the template list
     */
    test("Create Editor Template: check empty messages, validation messages and the template is presented in the list", async ({
        tester,
        page,
    }) => {
        const templates = new EditorTemplates(page, tester);
        const templateNameBadge = "Automation Template Badge";
        const templateNameEvent = "Automation Template Event";
        const templateNameEmail = "Automation Template Email";
        const templateNameFeedback = "Automation Template Feedback";
        const templateNameHomepage = "Automation Template Homepage";
        const templateNameActivity = "Automation Template Activity";
        const templateMoments = "Automation Template Moments";
        const templateNews = "Automation Template News";

        tester.isGoingTo("Check the editor template empty message is presented");
        await expect(page.locator(EditorTemplates.EMPTY_STATE)).toContainText(
            "No Badge Evidence templates have been created.",
        );
        await templates.clickTemplateTab("calendar-events");
        await expect(page.locator(EditorTemplates.EMPTY_STATE)).toContainText(
            "No Calendar Events templates have been created.",
        );
        await templates.clickTemplateTab("email");
        await expect(page.locator(EditorTemplates.EMPTY_STATE)).toContainText("No Email templates have been created.");
        await templates.clickTemplateTab("feedback");
        await expect(page.locator(EditorTemplates.EMPTY_STATE)).toContainText(
            "No Feedback templates have been created.",
        );
        await templates.clickTemplateTab("homepage");
        await expect(page.locator(EditorTemplates.EMPTY_STATE)).toContainText(
            "No Homepage templates have been created.",
        );
        await templates.clickTemplateTab("learning-activity");
        await expect(page.locator(EditorTemplates.EMPTY_STATE)).toContainText(
            "No Learning Activity templates have been created.",
        );
        await templates.clickTemplateTab("learning-moments");
        await expect(page.locator(EditorTemplates.EMPTY_STATE)).toContainText(
            "No Learning Moments templates have been created.",
        );
        await templates.clickTemplateTab("news");
        await expect(page.locator(EditorTemplates.EMPTY_STATE)).toContainText("No News templates have been created.");

        tester.isGoingTo("Click create new template link in the template list page: Badges");
        await templates.clickTemplateTab("badge-evidence");
        await templates.clickCreateNewTemplate("badge-evidence");
        await expect(page.locator("div.ck.ck-toolbar")).toBeVisible();
        await templates.clickSave();
        tester.isGoingTo("Check validation messages are shown");
        await expect(page.locator("#template-title-error")).toContainText("This field cannot be blank");
        await expect(page.locator("#template-body-error")).toContainText("This field cannot be blank");
        tester.isGoingTo("Type a template name,add a field name and body and save it");
        await templates.createTemplate(templateNameBadge, `Description for this template`, "Automation Body 1");
        tester.isGoingTo("Check the editor template is presented in the template list");
        await expect(page.getByTestId(`editor-templates-list-item-${templateNameBadge}`)).toContainText(
            `${templateNameBadge}`,
        );
        await expect(page.getByTestId(`editor-templates-list-item-${templateNameBadge}`)).toContainText(
            `Description for this template`,
        );

        tester.isGoingTo("Check the Add Template buttons redirects to the correct URL");
        await templates.clickAddTemplateButton("badge-evidence");
        await expect(page).toHaveURL("/adminv2/editor-templates/1/create");

        // navigate directly to the URL as the create link and button are already tested above
        tester.isGoingTo("Check the events templates works correctly: Calendar Events");
        await templates.gotoCreateTemplatePage(2);
        await templates.createTemplate(templateNameEvent, `Description for this template`, "Automation Body 2");
        tester.isGoingTo("Check the editor template is presented in the template list");
        await expect(page.getByTestId(`editor-templates-list-item-${templateNameEvent}`)).toContainText(
            templateNameEvent,
        );
        await expect(page.getByTestId(`editor-templates-list-item-${templateNameEvent}`)).toContainText(
            `Description for this template`,
        );

        tester.isGoingTo("Check the events templates works correctly: Email");
        await templates.gotoCreateTemplatePage(3);
        await templates.createTemplate(templateNameEmail, `Description for this template`, "Automation Body 3");
        tester.isGoingTo("Check the editor template is presented in the template list");
        await expect(page.getByTestId(`editor-templates-list-item-${templateNameEmail}`)).toContainText(
            templateNameEmail,
        );
        await expect(page.getByTestId(`editor-templates-list-item-${templateNameEmail}`)).toContainText(
            `Description for this template`,
        );

        tester.isGoingTo("Check the events templates works correctly: Feedback");
        await templates.gotoCreateTemplatePage(4);
        await templates.createTemplate(templateNameFeedback, `Description for this template`, "Automation Body 4");
        tester.isGoingTo("Check the editor template is presented in the template list");
        await expect(page.getByTestId(`editor-templates-list-item-${templateNameFeedback}`)).toContainText(
            templateNameFeedback,
        );
        await expect(page.getByTestId(`editor-templates-list-item-${templateNameFeedback}`)).toContainText(
            `Description for this template`,
        );

        tester.isGoingTo("Check the events templates works correctly: Homepage");
        await templates.gotoCreateTemplatePage(5);
        await templates.createTemplate(templateNameHomepage, `Description for this template`, "Automation Body 5");
        tester.isGoingTo("Check the editor template is presented in the template list");
        await expect(page.getByTestId(`editor-templates-list-item-${templateNameHomepage}`)).toContainText(
            templateNameHomepage,
        );
        await expect(page.getByTestId(`editor-templates-list-item-${templateNameHomepage}`)).toContainText(
            `Description for this template`,
        );

        tester.isGoingTo("Check the events templates works correctly: Learning Activity");
        await templates.gotoCreateTemplatePage(6);
        await templates.createTemplate(templateNameActivity, `Description for this template`, "Automation Body 6");
        tester.isGoingTo("Check the editor template is presented in the template list");
        await expect(page.getByTestId(`editor-templates-list-item-${templateNameActivity}`)).toContainText(
            templateNameActivity,
        );
        await expect(page.getByTestId(`editor-templates-list-item-${templateNameActivity}`)).toContainText(
            `Description for this template`,
        );

        tester.isGoingTo("Check the events templates works correctly: Learning Moments");
        await templates.gotoCreateTemplatePage(7);
        await templates.createTemplate(templateMoments, `Description for this template`, "Automation Body 7");
        tester.isGoingTo("Check the editor template is presented in the template list");
        await expect(page.getByTestId(`editor-templates-list-item-${templateMoments}`)).toContainText(templateMoments);
        await expect(page.getByTestId(`editor-templates-list-item-${templateMoments}`)).toContainText(
            `Description for this template`,
        );

        tester.isGoingTo("Check the events templates works correctly: News");
        await templates.gotoCreateTemplatePage(8);
        await templates.createTemplate(templateNews, `Description for this template`, "Automation Body 8");
        tester.isGoingTo("Check the editor template is presented in the template list");
        await expect(page.getByTestId(`editor-templates-list-item-${templateNews}`)).toContainText(templateNews);
        await expect(page.getByTestId(`editor-templates-list-item-${templateNews}`)).toContainText(
            `Description for this template`,
        );
    });

    /**
     * preview the template, edit and delete it
     */
    test("Preview, Edit and Delete an Editor Template", async ({ tester, page }) => {
        const templates = new EditorTemplates(page, tester);
        const templateName = "Automation Template Badge";
        const templateNameUpdate = "Automation Template Badge Updated";

        tester.isGoingTo("Click the template title in the template list, check its details are correct");
        await templates.clickTemplateTitle(templateName);
        await expect(page.locator("div.modal")).toContainText("Automation Body 1");
        await page.locator("button.close-modal").click();

        tester.isGoingTo("Click the modify button in the editor template lists page");
        await templates.clickEditIcon(templateName);
        await templates.typeTitle(templateNameUpdate);
        await templates.clickSave();
        tester.isGoingTo("Check the updated template is presented in the template list");
        await expect(page.getByTestId(`editor-templates-list-item-${templateNameUpdate}`)).toContainText(
            "Automation Template Badge Updated",
        );

        // add a new template to delete it
        tester.isGoingTo("check the template is successfully deleted");
        await templates.clickAddTemplateButton("badge-evidence");
        await templates.createTemplate(
            "Automation Template Badge to be deleted",
            `Description for this template`,
            "Automation Body to be deleted",
        );
        await templates.clickDeleteIcon("Automation Template Badge to be deleted");
        await page.locator("button.button.submit").click();

        await expect(page.getByTestId("toast")).toBeHidden();

        await expect(
            page.getByTestId(`editor-templates-list-item-Automation Template Badge to be deleted`),
        ).toHaveCount(0);
    });

    /**
     * Soft deleting data to not impact the tests
    //
     */
    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does impact next suite of tests",
        );
        await db.updateInDatabase(
            "editor_template",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Automation Template%" },
        );
    });
});
