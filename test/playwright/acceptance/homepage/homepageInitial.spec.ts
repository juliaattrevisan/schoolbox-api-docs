/**
 * test if the homepage is created only when user clicks the save button, add a few components(textbox, news and blog) into the homepage,
 * add content to the components and check its contents as different users, delete the component,
 * delete the homepage(by clicking cancel in the confirmation dialog it is not deleted, by confirming it is deleted)
 * 1. Provision sales.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance Homepage/ComponentsCest
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import TextboxForm from "@schoolboxTest/playwright/support/page/homepage/TextboxForm";
import Resources from "@schoolboxTest/playwright/support/page/resources/Resources";

test.describe("Homepage Initial Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    /**
     * when creating a homepage by filling all the mandatory fields the user forgets to click the save button
     * ensure the homepage was not created
     */

    test("create a homepage fail", async ({ tester, page }) => {
        const createFolder = new CreateFolder(page);
        const folderName = "Test Homepage";
        const homepageTemplateOption = "Blank";
        tester.isGoingTo("create a homepage folder");
        await tester.loginAsAdmin();
        await createFolder.goto();
        tester.isGoingTo("type a folder name, select a template option and set access permissions");
        await createFolder.typeName(folderName);
        await createFolder.setHomepageTemplate(homepageTemplateOption);
        await createFolder.setAccessPermission(1, "write");
        await createFolder.setAccessPermission(2, "write");
        await createFolder.setAccessPermission(3, "read");
        await createFolder.setAccessPermission(4, "read");
        await createFolder.setAccessPermission(5, "read");
        await createFolder.setAccessPermission(6, "read");
        tester.isGoingTo("Forget to submit");
        tester.isGoingTo("navigate to the resources page and check the new homepage folder is not presented");
        await createFolder.gotoResources();
        await expect(page.locator(`a.jstree-anchor:has-text("${folderName}")`)).not.toBeVisible();
    });

    /**
     * creates a homepage successfully
     */

    test("create a homepage", async ({ tester, page }) => {
        const createFolder = new CreateFolder(page);
        const folderName = "Test Homepage";
        const homepageTemplateOption = "Blank";
        tester.isGoingTo("create a homepage folder");
        await tester.loginAsAdmin();
        await createFolder.goto();
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
        tester.isGoingTo("navigate to the resources page and check the new homepage folder is presented");
        await createFolder.gotoResources();
        await expect(page.locator(`a.jstree-anchor:has-text("${folderName}")`)).toBeVisible();
    });

    /**
     * add a few components(textbox, news and blog) into the homepage and check it is added successfully
     */

    test("add components", async ({ tester, page }) => {
        const createFolder = new CreateFolder(page);
        const folderName = "Test Homepage";
        const resources = new Resources(page);
        const homepage = new Homepage(page);
        tester.isGoingTo("navigate to the Test Homepage via resources browser");
        await createFolder.gotoResources();
        await expect(page.locator(`a.jstree-anchor:has-text("${folderName}")`)).toBeVisible();
        await resources.clickFolderName(folderName);
        await resources.clickMenuItem("home");
        await page.waitForTimeout(2000);
        tester.isGoingTo("Add a few components in the homepage");
        await homepage.addComponent("#tabs-sub-1", "Blog");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Blog_Homepage_Component_Controller"]`),
        ).toBeVisible();
        await page.reload();
        await homepage.addComponent("#tabs-sub-1", "News");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Comms_News_Component_Homepage_Controller"]`),
        ).toBeVisible();
        await page.reload();
        await homepage.addComponent("#tabs-sub-1", "Text Box");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Resource_Textbox_Component_Homepage_Controller"]`),
        ).toBeVisible();
    });

    /**
     * adds content into the components: blog, textbox and news
     * check the content is successfully added
     */

    test("use components", async ({ tester, page, db }) => {
        const createFolder = new CreateFolder(page);
        const folderName = "Test Homepage";
        const resources = new Resources(page);
        const homepage = new Homepage(page);
        const textbox = new TextboxForm(tester, page);
        tester.isGoingTo("Navigate to the Test Homepage via resources browser");
        await createFolder.gotoResources();
        await expect(page.locator(`a.jstree-anchor:has-text("${folderName}")`)).toBeVisible();
        await resources.clickFolderName(folderName);
        await resources.clickMenuItem("home");
        await page.waitForTimeout(2000);
        const homepageId = (await db.grabFromDatabase("folder", "id", {
            deleted_at: null,
            "name like": folderName,
        })) as number;
        tester.isGoingTo("Add a blog post");
        const layout = Homepage.ELEMENT_COMPONENT_LAYOUT_TOP;
        await homepage.clickComponentEmptyStateLink(layout, 3);
        await page.waitForTimeout(2000);
        await page.fill("input[name=heading]", "Test Blog Title");
        await page.click("input[type=submit]");
        await page.waitForTimeout(2000);
        await page.goto(`/homepage/${homepageId}`);
        tester.isGoingTo("Add a news article");
        await homepage.clickComponentEmptyStateLink(layout, 2);
        await page.fill("input[name=title]", "Test News Title");
        const body = page.locator(`#body`);
        await tester.fillCKEditorByLocator(body, "wow what interesting news");
        await page.click("button[id=news-submit]");
        await page.waitForTimeout(2000);
        await page.goto(`/homepage/${homepageId}`);
        tester.isGoingTo("Add a text box");
        await homepage.clickComponentEmptyStateLink(layout, 1);
        await textbox.fillHeading("Test Text Box Title");
        await textbox.submit();
        await page.waitForTimeout(2000);
        const textboxId = (await db.grabFromDatabase("component_instance", "id", {
            sequence: 1,
            identifier: homepageId,
        })) as number;
        await textbox.fillContent("wow what interesting Text box text", textboxId);
        await textbox.save();
        await page.waitForTimeout(2000);
        tester.isGoingTo("Verify the content is added");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Blog_Homepage_Component_Controller"]`),
        ).toBeVisible();
        await expect(
            page.locator(`div[class="component-container Schoolbox_Comms_News_Component_Homepage_Controller"]`),
        ).toBeVisible();
        await expect(
            page.locator(`div[class="component-container Schoolbox_Resource_Textbox_Component_Homepage_Controller"]`),
        ).toBeVisible();
        await expect(
            page.locator(`div[class="component-container Schoolbox_Blog_Homepage_Component_Controller"]`),
        ).toContainText("Test Blog Title");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Comms_News_Component_Homepage_Controller"]`),
        ).toContainText("Test News Title");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Resource_Textbox_Component_Homepage_Controller"]`),
        ).toContainText("Test Text Box Title");
    });

    /**
     * as a student navigate to the homepage and check if they can see the components and its contents
     */

    test("view components", async ({ tester, page }) => {
        const createFolder = new CreateFolder(page);
        const folderName = "Test Homepage";
        const resources = new Resources(page);
        const student = "Ms Jana Reed";
        tester.isGoingTo("Navigate to the Test Homepage via resources browser");
        await tester.impersonateViaModifyUserPage(student);
        await createFolder.gotoResources();
        await expect(page.locator(`a.jstree-anchor:has-text("${folderName}")`)).toBeVisible();
        await resources.clickFolderName(folderName);
        await resources.clickMenuItem("home");
        await page.waitForTimeout(2000);
        tester.isGoingTo("Verify the content is visible");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Blog_Homepage_Component_Controller"]`),
        ).toContainText("Test Blog Title");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Comms_News_Component_Homepage_Controller"]`),
        ).toContainText("Test News Title");
        await expect(
            page.locator(`div[class="component-container Schoolbox_Resource_Textbox_Component_Homepage_Controller"]`),
        ).toContainText("Test Text Box Title");
    });

    /*
     * delete the component and checks it is no longer presented in the page
     */

    test("delete components", async ({ tester, page }) => {
        const createFolder = new CreateFolder(page);
        const folderName = "Test Homepage";
        const resources = new Resources(page);
        const homepage = new Homepage(page);
        tester.isGoingTo("Navigate to the Test Homepage via resources browser");
        await createFolder.gotoResources();
        await expect(page.locator(`a.jstree-anchor:has-text("${folderName}")`)).toBeVisible();
        await resources.clickFolderName(folderName);
        await resources.clickMenuItem("home");
        await page.waitForTimeout(2000);
        tester.isGoingTo("Delete the components");
        await homepage.clickLockToggle();
        await homepage.clickComponentRemoveIcon();
        await homepage.clickRemoveConfirmationMessage();
        await expect(
            page.locator(`div[class="component-container Schoolbox_Resource_Textbox_Component_Homepage_Controller"]`),
        ).not.toBeVisible();
        await page.reload();
        await homepage.clickLockToggle();
        await homepage.clickComponentRemoveIcon();
        await homepage.clickRemoveConfirmationMessage();
        await page.reload();
        await homepage.clickLockToggle();
        await homepage.clickComponentRemoveIcon();
        await homepage.clickRemoveConfirmationMessage();
        await expect(
            page.locator(`div[class="component-container Schoolbox_Comms_News__Homepage_Component_Controller"]`),
        ).not.toBeVisible();
        await expect(
            page.locator(`div[class="component-container Schoolbox_Blog_Homepage_Component_Controller"]`),
        ).not.toBeVisible();
    });

    /**
     * click Cancel in the dialog when attempting to delete a homepage and ensure the homepage is not deleted
     */

    test("delete homepage fail", async ({ tester, page }) => {
        const createFolder = new CreateFolder(page);
        const folderName = "Test Homepage";
        const resources = new Resources(page);
        tester.isGoingTo(
            "Navigate to the Test Homepage via resources browser, click delete homepage but not confirm it",
        );
        await createFolder.gotoResources();
        await expect(page.locator(`a.jstree-anchor:has-text("${folderName}")`)).toBeVisible();
        await resources.clickFolderName(folderName);
        await resources.clickMenuItem("delete");
        await resources.clickModalCancelButton();
        await page.waitForTimeout(2000);
        await page.reload();
        await expect(page.locator(`a.jstree-anchor:has-text("${folderName}")`)).toBeVisible();
    });

    /**
     * click Delete in the dialog when attempting to delete a homepage and ensure the homepage is deleted
     */

    test("delete homepage", async ({ tester, page }) => {
        const createFolder = new CreateFolder(page);
        const folderName = "Test Homepage";
        const resources = new Resources(page);
        tester.isGoingTo("Navigate to the Test Homepage via resources browser and delete the homepage");
        await createFolder.gotoResources();
        await expect(page.locator(`a.jstree-anchor:has-text("${folderName}")`)).toBeVisible();
        await resources.clickFolderName(folderName);
        await resources.clickMenuItem("delete");
        await resources.clickModalDeleteButton();
        await page.waitForTimeout(2000);
        await page.reload();
        await expect(page.locator(`a.jstree-anchor:has-text("${folderName}")`)).not.toBeVisible();
    });

    /**
     * Soft deleting data to not impact the other tests
     */

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does impact next suite of tests",
        );
        await db.updateInDatabase("folder", { deleted_at: "2020-01-15 11:22:34" }, { "name like": "Test Homepage" });
        await db.updateInDatabase("blog", { deleted_at: "2020-01-15 11:22:34" }, { "heading like": "Test Blog Title" });
        await db.updateInDatabase(
            "textBox",
            { deleted_at: "2020-01-15 11:22:34" },
            { "heading like": "Test Text Box Title" },
        );
        await db.updateInDatabase(
            "comms_news_article",
            { deleted_at: "2020-01-15 11:22:34" },
            { "title like": "Test News Title" },
        );
    });
});
