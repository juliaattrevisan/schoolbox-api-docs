/**
 * This test checks if the Folder component is presented under the homepage
 */
import type { Locator } from "@playwright/test";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import Folders from "@schoolboxTest/playwright/support/page/homepage/FolderComponent";

test.describe("Folders Component Tests", () => {
    test.describe.configure({ mode: "serial" });

    let homepage: Homepage;
    let folders: Folders;
    let folderComponent: Locator;
    let componentId: number | undefined;
    let componentHomepageId: number | undefined;

    const folderTitle: string = "Automation Test Folder";
    const updatedFolderTitle: string = "Automation Updated Test Folder";
    const artworkFolderTitle: string = "Artwork";
    const classPhotosFolderTitle: string = "Class Photos";
    const homepageId: number = 55;
    const altSourceHomepageId: number = 188;

    test.beforeEach(async ({ tester, page }) => {
        homepage = new Homepage(tester, page);
        folders = new Folders(tester, page);
        await tester.loginAsAdmin();
    });

    test("Verify Folder component and click Add Folder", async ({ tester }) => {
        tester.isGoingTo("Open homepage");
        await homepage.goto(homepageId);

        tester.isGoingTo("Add Folder component");
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_RESOURCES, "Folders");

        folderComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        componentHomepageId = await Homepage.getComponentId(folderComponent);

        tester.isGoingTo(`Use Folder component: ${componentHomepageId}`);
        await homepage.seeInComponentHeading("Folders", Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);

        tester.isGoingTo("Verify Artwork and Class Photos folders are shown using data-test");
        await folders.verifyFolderExists(folderComponent, artworkFolderTitle);
        await folders.verifyFolderExists(folderComponent, classPhotosFolderTitle);

        tester.isGoingTo("Click Add Folder button");
        await folders.clickAddFolder(folderComponent);

        tester.isGoingTo("Create folder");
        await folders.fillFolderName(folderTitle);
        await folders.submitCreateFolder();

        tester.isGoingTo("Verify created folder is shown");
        await homepage.goto(homepageId);
        folderComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        await folders.verifyFolderExists(folderComponent, folderTitle);
    });

    test("Verify folder limit to 1", async ({ tester }) => {
        tester.isGoingTo("Navigate to homepage and configure the Folders component");
        await homepage.goto(homepageId);

        folderComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);

        tester.isGoingTo("Set folder limit to 1");
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(folderComponent);
        await Folders.setFolderLimit(folderComponent, "1");

        const visibleFolders = await Folders.getAllFolders(folderComponent);
        tester.isGoingTo(visibleFolders.toString());

        expect(visibleFolders).toEqual([artworkFolderTitle]);
    });
    test("Verify grid view shows folder card image", async ({ tester }) => {
        tester.isGoingTo("Navigate to homepage and configure the Folders component");
        await homepage.goto(homepageId);

        folderComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);

        tester.isGoingTo("Set folder view to Grid with limit 1");
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(folderComponent);
        await Folders.configureFolderSettings(folderComponent, "grid", "1", "Name", "asc");

        const cardImages = await Folders.getAllCardImages(folderComponent);
        tester.isGoingTo(`Card images shown: ${cardImages}`);

        expect(cardImages).toBe(1);
    });
    test("Verify folders are sorted by name ascending and descending", async ({ tester }) => {
        tester.isGoingTo("Navigate to homepage and configure the Folders component");
        await homepage.goto(homepageId);

        folderComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);

        tester.isGoingTo("Set view as List, limit All, sort by Name ascending");
        await Homepage.clickComponentSettings(folderComponent);
        await Folders.configureFolderSettings(folderComponent, "list", "0", "Name", "asc");

        let sortedFolders = await Folders.getAllFolders(folderComponent);
        tester.isGoingTo(sortedFolders.toString());

        expect(sortedFolders).toEqual([artworkFolderTitle, folderTitle, classPhotosFolderTitle]);

        tester.isGoingTo("Set view as List, limit All, sort by Name descending");
        await Homepage.clickComponentSettings(folderComponent);
        await Folders.configureFolderSettings(folderComponent, "list", "0", "Name", "desc");

        sortedFolders = await Folders.getAllFolders(folderComponent);
        tester.isGoingTo(sortedFolders.toString());

        expect(sortedFolders).toEqual([classPhotosFolderTitle, folderTitle, artworkFolderTitle]);
    });

    test("Verify folders are sorted by created ascending and descending", async ({ tester }) => {
        tester.isGoingTo("Navigate to homepage and configure the Folders component");
        await homepage.goto(homepageId);

        folderComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);

        tester.isGoingTo("Set view as List, limit All, sort by Created ascending");
        await Homepage.clickComponentSettings(folderComponent);
        await Folders.configureFolderSettings(folderComponent, "list", "0", "Created", "asc");

        let sortedFolders = await Folders.getAllFolders(folderComponent);
        tester.isGoingTo(sortedFolders.toString());

        expect(sortedFolders).toEqual([classPhotosFolderTitle, artworkFolderTitle, folderTitle]);

        tester.isGoingTo("Set view as List, limit All, sort by Created descending");
        await Homepage.clickComponentSettings(folderComponent);
        await Folders.configureFolderSettings(folderComponent, "list", "0", "Created", "desc");

        sortedFolders = await Folders.getAllFolders(folderComponent);
        tester.isGoingTo(sortedFolders.toString());

        expect(sortedFolders).toEqual([folderTitle, artworkFolderTitle, classPhotosFolderTitle]);
    });

    test("Verify folders are sorted by sequence ascending and descending", async ({ tester }) => {
        tester.isGoingTo("Navigate to homepage and configure the Folders component");
        await homepage.goto(homepageId);

        folderComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);

        tester.isGoingTo("Set view as List, limit All, sort by Sequence ascending");
        await Homepage.clickComponentSettings(folderComponent);
        await Folders.configureFolderSettings(folderComponent, "list", "0", "Sequence", "asc");

        let sortedFolders = await Folders.getAllFolders(folderComponent);
        tester.isGoingTo(sortedFolders.toString());

        expect(sortedFolders).toEqual([classPhotosFolderTitle, artworkFolderTitle, folderTitle]);

        tester.isGoingTo("Set view as List, limit All, sort by Sequence descending");
        await Homepage.clickComponentSettings(folderComponent);
        await Folders.configureFolderSettings(folderComponent, "list", "0", "Sequence", "desc");

        sortedFolders = await Folders.getAllFolders(folderComponent);
        tester.isGoingTo(sortedFolders.toString());

        expect(sortedFolders).toEqual([folderTitle, artworkFolderTitle, classPhotosFolderTitle]);
    });

    test("Verify existing folder title can be updated", async ({ tester }) => {
        tester.isGoingTo("Navigate to homepage and locate the Folders component");
        await homepage.goto(homepageId);

        folderComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);

        tester.isGoingTo("Update Automation Test Folder title");
        await folders.updateExistingFolderTitle(folderComponent, folderTitle, updatedFolderTitle);

        tester.isGoingTo("Verify updated folder title is shown");
        await homepage.goto(homepageId);
        folderComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);

        await folders.verifyFolderExists(folderComponent, updatedFolderTitle);
    });
    test("Verify adding Folder component with alt source", async ({ tester }) => {
        tester.isGoingTo("Navigate to homepage and add Folders component");
        await homepage.goto(altSourceHomepageId);

        tester.isGoingTo("Add Folders component");
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_RESOURCES, "Folders");

        folderComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        componentId = await Homepage.getComponentId(folderComponent);

        tester.isGoingTo(`Use Folder component: ${componentId}`);

        tester.isGoingTo("Open Folder component settings");
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(folderComponent);

        tester.isGoingTo("Select existing 3RW folder source");
        await folders.selectAltSourceFolders(folderComponent);

        const foldersFrom3RW = await Folders.getAllFolders(folderComponent);
        tester.isGoingTo(foldersFrom3RW.toString());

        expect(foldersFrom3RW).toEqual([artworkFolderTitle, updatedFolderTitle, classPhotosFolderTitle]);
    });

    test("Verify Jana Reed can see folders but not Add Folder button or Folder component title", async ({ tester }) => {
        tester.isGoingTo("Update Show Title to Read-Only Users setting");
        await homepage.goto(homepageId);

        folderComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(folderComponent);
        await folders.clickShowComponentTitle(folderComponent);
        await folders.saveComponentSettings(folderComponent);

        tester.isGoingTo("Impersonate Jana Reed");
        await tester.impersonate("Jana Reed");

        tester.isGoingTo("Navigate to Jana Reed homepage");
        await homepage.goto(homepageId);

        folderComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);

        tester.isGoingTo("Verify Folder component title is not shown");
        await Folders.verifyComponentTitleNotVisible(folderComponent);

        tester.isGoingTo("Verify Add Folder button is not shown");
        await expect(folderComponent.getByTestId(Folders.DATA_TEST_ADD_FOLDER_BUTTON)).toBeHidden();

        tester.isGoingTo("Verify folders are visible");
        await folders.verifyFolderExists(folderComponent, updatedFolderTitle);
        await folders.verifyFolderExists(folderComponent, artworkFolderTitle);
        await folders.verifyFolderExists(folderComponent, classPhotosFolderTitle);
    });

    test.afterAll(async ({ baseTester, db, api }) => {
        baseTester.isGoingTo("clean up: remove Folders component and delete created folder rows");

        await db.dontHaveInDatabase("folder", { name: updatedFolderTitle, deleted_at: null });
        await db.dontHaveInDatabase("folder", { name: folderTitle, deleted_at: null });
        if (componentHomepageId !== undefined) {
            await api.deleteHomepageComponent(homepageId, componentHomepageId);
        }

        if (componentId !== undefined) {
            await api.deleteHomepageComponent(altSourceHomepageId, componentId);
        }
    });
});
