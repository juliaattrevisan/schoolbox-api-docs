import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CreateLessonPlanTemplate from "@schoolboxTest/playwright/support/page/admin/lms/LessonPlanTemplates/CreateLessonPlanTemplate";
import LessonPlanTemplateList from "@schoolboxTest/playwright/support/page/admin/lms/LessonPlanTemplates/LessonPlanTemplateList";
import UpdateLessonPlanTemplate from "@schoolboxTest/playwright/support/page/admin/lms/LessonPlanTemplates/UpdateLessonPlanTemplate";

/**
 * This test adds, updates and deletes a lesson plan template
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. npx playwright test lessonPlanTemplates.spec.ts
 */

test.describe("Lesson Plan Templates", () => {
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
        const templateList = new LessonPlanTemplateList(tester, page);
        await templateList.goto();
    });

    /**
     * on adminv2/lesson-plan/template page click Create button
     * Type a Title
     * Click Add a new field button
     * Add a Field title
     * Add a Field Body
     * Click Save
     * Ensure the Lesson Plan Template is presented in the Lesson PLan Template list
     */
    test("Create Lesson Plan Template", async ({ tester, page }) => {
        const templates = new CreateLessonPlanTemplate(page, tester);
        const templateList = new LessonPlanTemplateList(tester, page);
        const templateName = "Automation Template 1";

        tester.isGoingTo("Click add a lesson plan template button in the lesson plan template lists page");
        await templateList.clickAddLessonPlanTemplateButton();
        tester.isGoingTo("Type a template name,add a field name and body and save it");
        await templates.typeName(templateName);
        await templates.typeFieldTile(1, "Automation Field 1");
        await templates.fillBody(1, "Automation Body 1");
        await templates.clickSaveButton();
        tester.isGoingTo("Check the lesson pan template is presented in the template list");
        await expect(page.locator("div.admin-content")).toContainText(templateName);
    });

    /**
     * on adminv2/lesson-plan/template page click the Modify button
     * Update the Title
     * Click Add a new field button
     * Update the existing field title and body
     * Add a new Field with field title and body
     * Delete a Field
     * Click Save
     * Click the template in the list
     * Ensure the dialog is presented with the fields properly presented
     */

    test("Update Lesson Plan Template", async ({ tester, page, db }) => {
        const templates = new CreateLessonPlanTemplate(page, tester);
        const templateList = new LessonPlanTemplateList(tester, page);
        const templateUpdate = new UpdateLessonPlanTemplate(page, tester);

        tester.isGoingTo("Click the modify button in the lesson plan template lists page");
        const templateId = (await db.grabFromDatabase("lesson_plan_template", "id", {
            deleted_at: null,
            "title like": "Automation Template 1",
        })) as number;
        await templateList.clickModifyLessonPlanTemplateButton(templateId);
        tester.isGoingTo("Update the field title and body");
        const templateFieldId = (await db.grabFromDatabase("lesson_plan_template_field", "id", {
            deleted_at: null,
            "title like": "Automation Field 1",
        })) as number;
        await templateUpdate.typeFieldTile(templateFieldId, "Automation Template 1 Updated");
        await templateUpdate.fillBody(templateFieldId, "Automation Body 1 Updated");
        tester.isGoingTo("Add a new field, type a filed title and body and save it");
        await templates.clickAddNewFieldButton();
        await templates.typeFieldTile(1, "Automation Field 2");
        await templates.fillBody(1, "Automation Body 2");
        await templates.clickSaveButton();
        tester.isGoingTo("Click the template title in the template list and check the fields are updated");
        await templateList.clickLessonPlanTemplateTitle(templateId);
        await expect(page.locator(`#lpt-preview-${templateId}`)).toContainText("Automation Template 1 Updated");
        await expect(page.locator(`#lpt-preview-${templateId}`)).toContainText("Automation Body 1 Updated");
        await expect(page.locator(`#lpt-preview-${templateId}`)).toContainText("Automation Field 2");
        await expect(page.locator(`#lpt-preview-${templateId}`)).toContainText("Automation Body 2");
        await templateList.clickCloseModal(templateId);
        tester.isGoingTo("Modify a lesson plan template, delete a field, save it");
        await templateList.clickModifyLessonPlanTemplateButton(templateId);
        await templates.clickDeleteButton();
        await templates.clickConfirmDeleteButton();
        await templates.clickSaveButton();
        tester.isGoingTo("Click the template title in the template list and check the field deleted is not presented");
        await templateList.clickLessonPlanTemplateTitle(templateId);
        await expect(page.locator(`#lpt-preview-${templateId}`)).not.toContainText("Automation Template 1 Updated");
        await expect(page.locator(`#lpt-preview-${templateId}`)).not.toContainText("Automation Body 1 Updated");
        await expect(page.locator(`#lpt-preview-${templateId}`)).toContainText("Automation Field 2");
        await expect(page.locator(`#lpt-preview-${templateId}`)).toContainText("Automation Body 2");
        await templateList.clickCloseModal(templateId);
        await expect(page.locator(`#lpt-preview-${templateId}`)).not.toContainText(
            "This field is not visible to students",
        );
        tester.isGoingTo("Modify a lesson plan template, uncheck the visible to students and save it");
        await templateList.clickModifyLessonPlanTemplateButton(templateId);
        const templateFieldId2 = (await db.grabFromDatabase("lesson_plan_template_field", "id", {
            deleted_at: null,
            "title like": "Automation Field 2",
        })) as number;
        await templates.clickCheckboxVisibleStudentsUpdatingTemplate(templateFieldId2);
        await templates.clickSaveButton();
        tester.isGoingTo("Check the template is presenting the message invisible to students on the modal");
        await templateList.clickLessonPlanTemplateTitle(templateId);
        await expect(page.locator(`#lpt-preview-${templateId}`)).toContainText("This field is not visible to students");
    });

    /**
     * on adminv2/lesson-plan/template page click the Delete button
     * Ensure the Lesson Plan Template is no longer presented in the Lesson PLan Template list
     */

    test("Delete Lesson Plan Template", async ({ tester, page, db }) => {
        const templates = new CreateLessonPlanTemplate(page, tester);
        const templateList = new LessonPlanTemplateList(tester, page);

        tester.isGoingTo("Disable a lesson plan template and check it is no longer presented in the list");
        const templateId = (await db.grabFromDatabase("lesson_plan_template", "id", {
            deleted_at: null,
            "title like": "Automation Template 1",
        })) as number;
        await templateList.clickLessonPlanTemplateMenu(templateId);
        await templateList.clickDisableEnableVisibility(templateId);
        await templateList.clickLessonPlanTemplateMenu(templateId);
        await expect(page.locator(`#lpt-${templateId}.f-dropdown`)).toContainText("Enable visibility");
        tester.isGoingTo("Enable a lesson plan template");
        await templateList.clickLessonPlanTemplateMenu(templateId);
        await templateList.clickDisableEnableVisibility(templateId);
        await templateList.clickLessonPlanTemplateMenu(templateId);
        await expect(page.locator(`#lpt-${templateId}.f-dropdown`)).toContainText("Disable visibility");
        tester.isGoingTo("Delete a lesson plan template and check it is no longer presented in the list");
        await page.reload();
        await templateList.clickLessonPlanTemplateMenu(templateId);
        await templateList.clickDeleteTemplate(templateId);
        await templates.clickConfirmDeleteButton();
        await expect(page.locator(`#lpt-${templateId}.f-dropdown`)).not.toContainText("Automation Template 1");
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
            "lesson_plan_template_field",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Automation Field%" },
        );
        await db.updateInDatabase(
            "lesson_plan_template",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Automation Template%" },
        );
    });
});
