import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import type { Page } from "@playwright/test";
import type DatabaseHelper from "@schoolboxTest/playwright/support/DatabaseHelper";
import Resources from "@schoolboxTest/playwright/support/page/resources/Resources";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import AssessmentForm from "@schoolboxTest/playwright/support/page/dueWork/AssessmentForm";
import QuizSettingPage from "@schoolboxTest/playwright/support/page/quiz/QuizSettingPage";

/**
 * covenience function to create a homepage
 * @param tester - The tester
 * @param page - The page
 * @param db - The database helper
 * @param folderName - The folder name
 */
async function createClassPage(
    tester: AcceptanceTester,
    page: Page,
    db: DatabaseHelper,
    folderName: string,
): Promise<void> {
    const createFolder = new CreateFolder(page);
    const resources = new Resources(page);

    tester.isGoingTo("Create a homepage");
    await createFolder.goto();
    await page.waitForLoadState("domcontentloaded");
    const homepageTemplateOption = "Blank";
    tester.isGoingTo("type a folder name, select a template option and set access permissions");
    await createFolder.typeName(folderName);
    await createFolder.setHomepageTemplate(homepageTemplateOption);
    await createFolder.setAccessPermission(1, "write");
    await createFolder.setAccessPermission(2, "write");
    await createFolder.setAccessPermission(3, "read");
    await createFolder.setAccessPermission(4, "read");
    await createFolder.setAccessPermission(5, "read");
    await createFolder.setAccessPermission(6, "read");
    await createFolder.submit();

    tester.isGoingTo("Navigate to the homepage");
    await resources.goto();
    await expect(page.locator(`li[title="${folderName}"]`)).toBeVisible();
    const folderId = (await db.grabFromDatabase("folder", "id", { deleted_at: null, name: folderName })) as string;
    expect(folderId).not.toBe(null);
    await resources.clickFolderName(folderName);
}

test.describe("Quiz Create Tests", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });

    test.beforeEach(async ({ tester }) => {
        await tester.logout();
        await tester.loginAsAdmin();
    });

    test("Create a quiz", async ({ tester, page, db }) => {
        const resources = new Resources(page);
        const homepage = new Homepage(page);
        const assessmentForm = new AssessmentForm(tester, page);
        const quiz = new QuizSettingPage(tester, page);

        const quizName = "Quiz 1";
        const folderName = "Test Class Homepage Quiz";

        tester.isGoingTo("create a homepage, navigate to the class page and add a quiz component");
        await createClassPage(tester, page, db, folderName);
        await resources.goto();
        await expect(page.locator(`li[title="${folderName}"]`)).toBeVisible();
        const folderId = (await db.grabFromDatabase("folder", "id", { deleted_at: null, name: folderName })) as string;
        expect(folderId).not.toBe(null);
        await resources.clickFolderName(folderName);
        await page.waitForLoadState("domcontentloaded");
        await resources.clickMenuItem("home");
        await page.waitForLoadState("domcontentloaded");
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_LEARNING, "Quizzes");

        tester.isGoingTo("Create a quiz");
        const layout = Homepage.ELEMENT_COMPONENT_LAYOUT_TOP;
        const position = 1;
        await homepage.clickComponentEmptyStateLink(layout, position);
        await quiz.setTitle(quizName);
        await assessmentForm.setWorkType(AssessmentForm.WORKTYPE_ASSESSMENT_TASK);
        await quiz.create();

        // check if I can modify this locator or if this is coming from learnosity
        // await page.locator("span.lrn-author-button-text").getByText("Create item").click();
        // await page.waitForTimeout(500);
        // await page.locator("button.lrn-author-widget-button").click();
        // await page.waitForTimeout(500);
        // await page.locator("a.lrn-qe-a.lrn-qe-tile-preview").first().click();
        // await page.waitForTimeout(500);
        // await page
        //     .locator("button.lrn-btn-save.lrn-btn.lrn-btn-legacy.lrn-btn-primary.lrn-btn-primary-legacy")
        //     .first()
        //     .click();

        tester.isGoingTo("navigate to the homepage and ensure the quiz is presented");
        await homepage.goto(parseInt(folderId, 10));
        const quizId = (await db.grabFromDatabase("assessment", "id", {
            deleted_at: null,
            "title like": quizName,
        })) as number;
        await expect(page.getByTestId(`component-assessment-title-${quizId}`)).toContainText($quizName);
    });

    test("Modify a quiz", async ({ tester, page, db }) => {});

    test("Delete a quiz", async ({ tester, page, db }) => {});
});
