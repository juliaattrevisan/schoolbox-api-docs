import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import EditorTemplates from "@schoolboxTest/playwright/support/page/admin/design/EditorTemplates";

/**
 * This test adds, updates and deletes a template
 * Applies to editor templates for each feature
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. npx playwright test editorTemplates
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

        tester.isGoingTo("Check the editor template empty message is presented");
        await templates.verifyEmptyStateMessage();

        tester.isGoingTo("Click create new template link in the template list page: Badges");
        await templates.clickTemplateTab("badge-evidence");
        await templates.clickCreateNewTemplate("badge-evidence");
        await expect(page.locator("div.ck.ck-toolbar")).toBeVisible();
        await templates.clickSave();
        tester.isGoingTo("Check validation messages are shown");
        await expect(page.locator("#template-title-error")).toContainText("This field cannot be blank");
        await expect(page.locator("#template-body-error")).toContainText("This field cannot be blank");
        tester.isGoingTo("Type a template name,add a field name and body and save it");

        await templates.verifyCreateTemplates();
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
