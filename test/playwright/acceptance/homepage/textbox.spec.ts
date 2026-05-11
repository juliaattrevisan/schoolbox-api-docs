/**
 * creates various textboxes, add a textbox in a homepage and keep a few hidden,
 * logged in as different users check whether they are able to view the textboxes or not
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test textbox.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import ManageAllTextbox from "@schoolboxTest/playwright/support/page/homepage/ManageAllTextbox";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import Resources from "@schoolboxTest/playwright/support/page/resources/Resources";
import TextboxForm from "@schoolboxTest/playwright/support/page/homepage/TextboxForm";
import SearchPage from "@schoolboxTest/playwright/support/page/search/SearchPage";
import EditorTemplate from "@schoolboxTest/playwright/support/page/admin/design/EditorTemplates";

test.describe("Textbox Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    /**
     * create a homepage
     */

    test("create a homepage", async ({ tester, page, db }) => {
        const createFolder = new CreateFolder(tester, page);
        const folderName = "Test TextBox Homepage";
        const homepageTemplateOption = "Blank";
        const schoolCode = "";

        // sets write access permission for Administration and Teacher, read access permission for senior student, parent, junior student and middle student
        await createFolder.createFolder(
            folderName,
            homepageTemplateOption,
            db,
            [
                [1, "write"],
                [2, "write"],
                [3, "read"],
                [4, "read"],
                [5, "read"],
                [6, "read"],
            ],
            schoolCode,
        );

        tester.isGoingTo("navigate to the resources page and check the new homepage folder is presented");
        await createFolder.gotoResources();
        await expect(page.locator("#container")).toContainText(folderName);
    });

    //*
    // as alaress I will navigate to a homepage
    // add a textbox component with heading and content
    // and verify it is properly presented
    // */

    test("create a textbox", async ({ tester, page, db }) => {
        const createFolder = new CreateFolder(tester, page);
        const resources = new Resources(page);
        const manageAll = new ManageAllTextbox(tester, page);
        const homepage = new Homepage(tester, page);
        const textbox = new TextboxForm(tester, page);
        const folderName = "Test TextBox Homepage";
        const heading = "Test Text Box Title";
        const content = "I am textbox content";

        const editorTemplate = new EditorTemplate(page, tester);
        tester.isGoingTo("Create a editor template to use as homepage text box description");
        await editorTemplate.gotoCreateTemplatePage(5);
        await editorTemplate.createTemplate("Homepage Automation Template", "This is an automation template", content);

        tester.isGoingTo("Get the folder id");
        const homepageId = (await db.grabFromDatabase("folder", "id", {
            deleted_at: null,
            "name LIKE": folderName,
        })) as number;

        tester.isGoingTo("Navigate to the Test TextBox Homepage via resources browser");
        await createFolder.gotoResources();
        await resources.clickFolderName(folderName);
        await resources.clickMenuItem("home");

        tester.isGoingTo("Add a new component in the homepage");
        await homepage.addComponent("#tabs-sub-1", "Text Box");
        tester.isGoingTo("Enter content into the new component");
        await page.waitForSelector('sbx-icon[name="textbox"]');
        const layout = Homepage.ELEMENT_COMPONENT_LAYOUT_TOP;
        const position = 1;
        await homepage.clickComponentEmptyStateLink(layout, position);
        await textbox.fillHeading(heading);
        await textbox.submit();
        tester.isGoingTo("select the text box template in the textbox CKEditor");
        await textbox.clickEditorTemplateCKEditor("Homepage Automation Template");
        await textbox.save();
        tester.isGoingTo("Verify what I entered is the heading and body");
        await expect(page.locator("#container")).toContainText(heading);
        await expect(page.locator("#container")).toContainText(content);

        tester.isGoingTo("Create another 2 text boxes");
        await manageAll.clickManageAll();
        await manageAll.clickCreateTextBox();
        const textBoxCount = await db.countInDatabase("textbox", {
            folder_id: homepageId,
        });
        await manageAll.typeTextBoxHeading(textBoxCount, "Second Text Box");
        await manageAll.typeTextBoxContent(textBoxCount, "Test");
        await manageAll.clickCreateTextBox();
        const textBoxItem = textBoxCount + 1;
        await manageAll.typeTextBoxHeading(textBoxItem, "Third Text Box");
        await manageAll.typeTextBoxContent(textBoxItem, "Jana Reed");
        await manageAll.clickSaveAll();

        await tester.rebuildSearchIndex();
    });

    /**
     * Verifies which textbox different users can or cannot view
     *
     * as teacher and student I will navigate to a homepage,
     * check the textbox I can view
     * and also which textboxes are presented for me when I search via search bar
     */

    test("textbox visibility", async ({ tester, page, db }) => {
        const folderName = "Test TextBox Homepage";
        const manageAll = new ManageAllTextbox(tester, page);
        const searchPage = new SearchPage(page);
        const homepage = new Homepage(tester, page);
        const textbox = new TextboxForm(tester, page);

        tester.isGoingTo("As Jack Coles search for a  textbox and ensure it is presented in the search results grid");
        await tester.impersonate("Jack Coles");
        await tester.common.search("Jana Reed");
        await searchPage.clearFilters();
        await searchPage.clickFilterType("textbox");
        await searchPage.clickApplyFilters();
        await page.waitForSelector("#search-page-container");
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Jana Reed");
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Third Text Box");
        await searchPage.inputKeyword("Test");
        await searchPage.clearFilters();
        await searchPage.clickFilterType("textbox");
        await searchPage.clickApplyFilters();
        await page.waitForSelector("#search-page-container");
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Second Text Box");
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Test");
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Test Text Box Title");
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("I am textbox content");

        tester.isGoingTo(
            "As Jana Reed search for a  textbox and check whether it is presented in the search results grid or not",
        );
        await tester.impersonate("Jana Reed");
        await tester.common.search("'Jana Reed'");
        await searchPage.clearFilters();
        await tester.common.clickSearchTypeCheckbox("textbox");
        await searchPage.clickApplyFilters();
        await page.waitForSelector("#search-page-container");
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).not.toContainText("Jana Reed");
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).not.toContainText("Third Text Box");
        await searchPage.inputKeyword("Test");
        await searchPage.clearFilters();
        await searchPage.clickFilterType("textbox");
        await searchPage.clickApplyFilters();
        await page.waitForSelector("#search-page-container");
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).not.toContainText("Second Text Box");
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Test Text Box Title");
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("I am textbox content");

        tester.isGoingTo("As Jack Coles navigate to the Test TextBox Homepage");
        await tester.impersonate("Jack Coles");
        const homepageId = (await db.grabFromDatabase("folder", "id", {
            deleted_at: null,
            "name LIKE": folderName,
        })) as number;
        await page.goto(`/homepage/${homepageId}`);
        tester.isGoingTo("Unlock the components and edit the textbox settings");
        await homepage.clickLockToggle();
        const component = page.locator(Homepage.locateComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1));
        await Homepage.clickComponentSettings(component);
        await homepage.typeTextBoxHeading("Jana to be hidden");
        tester.isGoingTo("Hide the textbox title from read only users");
        const componentId = (await db.grabFromDatabase("component_instance", "id", {
            identifier: homepageId,
        })) as number;
        await homepage.uncheckComponentCheckbox(componentId, "showComponentTitle");
        await manageAll.clickSaveAll();
        tester.isGoingTo("Update the textbox content");
        await textbox.modify();
        tester.isGoingTo("get the component id");
        const textboxId = (await db.grabFromDatabase("component_instance", "id", {
            identifier: homepageId,
        })) as number;
        await textbox.fillContent("Jamal to be presented", textboxId);
        await textbox.save();

        await tester.rebuildSearchIndex();

        tester.isGoingTo("As Jana Reed search for textboxes and check whether its is presented or not");
        await tester.impersonate("Jana Reed");
        await tester.common.search("Jamal");
        await searchPage.clearFilters();
        await tester.common.clickSearchTypeCheckbox("textbox");
        await searchPage.clickApplyFilters();
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Jamal to be presented");
        await searchPage.inputKeyword("Jana");
        await searchPage.clearFilters();
        await searchPage.clickApplyFilters();
        await page.waitForSelector("#search-page-container");
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Jamal to be presented");
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).toContainText("Untitled textbox");
        await expect(page.locator('div[class="l-flex-col l-gap-m"]')).not.toContainText("Jana to be hidden");
        tester.isGoingTo("Navigate to the Test TextBox Homepage to check whether the textbox is presented or not");
        await page.goto(`/homepage/${homepageId}`);
        await expect(page.locator("#container")).toContainText("Jamal to be presented");
        await expect(page.locator("#container")).not.toContainText("Jana to be hidden");
    });

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });

    /**
     * Soft deleting data to not impact the tests
     */
    test.afterAll(async ({ baseTester, db, api }) => {
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does impact next suite of tests",
        );

        await api.deleteFoldersByName(db, "Test TextBox Homepage");

        await db.updateInDatabase(
            "textbox",
            { deleted_at: "2022-01-15 11:22:34" },
            { heading: "Test Text Box Title", deleted_at: null },
        );
        await db.updateInDatabase(
            "textbox",
            { deleted_at: "2022-01-15 11:22:34" },
            { heading: "Second Text Box", deleted_at: null },
        );
        await db.updateInDatabase(
            "textbox",
            { deleted_at: "2022-01-15 11:22:34" },
            { heading: "Third Text Box", deleted_at: null },
        );
        await db.updateInDatabase(
            "editor_template",
            { deleted_at: "2022-01-15 11:22:34" },
            { "title like": "Homepage Automation%", deleted_at: null },
        );
    });
});
