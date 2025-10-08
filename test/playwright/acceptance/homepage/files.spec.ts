/**
 * This test checks if the File components are presented under the homepage
 */
import type { Locator } from "@playwright/test";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import Files from "@schoolboxTest/playwright/support/page/homepage/Files";

test.describe("Files Component Tests", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });

    let componentId: number;
    let componentEnglishId: number;
    let homepage: Homepage;
    let files: Files;
    let filesComponent: Locator;
    const newFileTitle: string = "Text Automation Updated File";
    const fileTitle: string = "Test Automation File";
    const fileDescription: string = "Description for File";

    test.beforeEach(async ({ tester, page }) => {
        homepage = new Homepage(tester, page);
        files = new Files(tester, page);
        await tester.loginAsAdmin();
    });

    test("Verify Add File", async ({ tester, page }) => {
        tester.isGoingTo("Homepage, open tab panel, click components tab");
        await homepage.goto(83);
        tester.isGoingTo("Add Files component");
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_RESOURCES, "Files");
        filesComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        componentId = await Homepage.getComponentId(filesComponent);
        tester.isGoingTo(`Use component: ${componentId}`);
        await homepage.seeInComponentHeading("Files", Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        tester.isGoingTo("Check the Three Files exists ");
        await Files.verifyFileTitle(filesComponent, "Glasses", false);
        await Files.verifyFileTitle(filesComponent, "Where did English come from?", false);
        await Files.verifyFileTitle(filesComponent, "Spelling tips", false);
        tester.isGoingTo("Add a new file via 'Select File'.");
        await Files.selectFile(page, filesComponent, fileTitle, fileDescription, false);
        tester.isGoingTo("Verify File added Translucent Title ");
        await Files.verifyFileTitle(filesComponent, fileTitle, true);
    });

    test("Verify Sorting Image Files", async ({ tester }) => {
        tester.isGoingTo("Navigate to 9A English homepage and configure the Files component");
        await homepage.goto(83);
        filesComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(filesComponent);
        tester.isGoingTo("Filter by Image and verify sorting.");
        await Files.sortImageFiles(filesComponent);
        const sortedImageFiles = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(`Current sort (Images): ${sortedImageFiles.join(" | ")}`);
        expect(sortedImageFiles).toEqual([fileTitle, "Glasses"]);
    });

    test("Verify Sorting Document and Video Files", async ({ tester }) => {
        tester.isGoingTo("Navigate to 9A English homepage and configure the Files component");
        await homepage.goto(83);
        filesComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(filesComponent);
        tester.isGoingTo("Filter by Documents & Videos and verify sorting.");
        await Files.sortVideoAndDocumentFiles(filesComponent);
        const sortedDocumentAndVideoFiles: string[] = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(`Current sort (Docs & Videos): ${sortedDocumentAndVideoFiles.join(" | ")}`);
        expect(sortedDocumentAndVideoFiles).toEqual(["Where did English come from?", "Spelling tips"]);

        tester.isGoingTo("Sort by Name (ascending).");
        await Homepage.clickComponentSettings(filesComponent);
        await Files.sortFilesAsc(filesComponent, "Name", "asc");
        const sortedFilesByNameAsc: string[] = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(`Sort by Name ASC: ${sortedFilesByNameAsc.join(" | ")}`);
        expect(sortedFilesByNameAsc).toEqual(["Glasses", "Spelling tips", fileTitle, "Where did English come from?"]);

        tester.isGoingTo("Sort by Name (descending).");
        await Homepage.clickComponentSettings(filesComponent);
        await Files.sortFilesDesc(filesComponent, "Name", "desc");
        const sortedFilesByNameDesc: string[] = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(`Sort by Name DESC: ${sortedFilesByNameDesc.join(" | ")}`);
        expect(sortedFilesByNameDesc).toEqual(["Where did English come from?", fileTitle, "Spelling tips", "Glasses"]);

        tester.isGoingTo("Sort by Date (ascending).");
        await Homepage.clickComponentSettings(filesComponent);
        await Files.sortFilesDesc(filesComponent, "Date", "asc");
        const sortedFilesByDateAsc: string[] = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(`Sort by Date ASC: ${sortedFilesByDateAsc.join(" | ")}`);
        expect(sortedFilesByDateAsc).toEqual(["Spelling tips", "Where did English come from?", "Glasses", fileTitle]);

        tester.isGoingTo("Sort by Date (descending).");
        await Homepage.clickComponentSettings(filesComponent);
        await Files.sortFilesDesc(filesComponent, "Date", "desc");
        const sortedFilesByDateDesc: string[] = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(`Sort by Date DESC: ${sortedFilesByDateDesc.join(" | ")}`);
        expect(sortedFilesByDateDesc).toEqual([fileTitle, "Glasses", "Where did English come from?", "Spelling tips"]);

        tester.isGoingTo("Set file limit to 2 and verify only two entries are shown.");
        await Homepage.clickComponentSettings(filesComponent);
        await Files.setFileLimit(filesComponent, "2");
        const setLimit: string[] = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(`Current files (limited to 2): ${setLimit.join(" | ")}`);
        expect(setLimit).toEqual([fileTitle, "Glasses"]);
        tester.isGoingTo("Save component settings and verify description of the added file.");
        await Homepage.clickComponentSettings(filesComponent);
        await Files.updateFileComponentSetting(filesComponent);
        await Files.checkFileDescription(filesComponent, fileTitle, fileDescription);
    });

    test("Verify Update file", async ({ tester, db }) => {
        tester.isGoingTo("Fetch file id for the file we created, using its description.");
        const fileId = (await db.grabFromDatabase("file", "id", {
            deleted_at: null,
            description: fileTitle,
        })) as number;

        tester.isGoingTo("Navigate to 9A English homepage and update a File in the Files component");
        await homepage.goto(83);
        tester.isGoingTo("Modify File Title");
        filesComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        await files.modifyFile(filesComponent, fileId, newFileTitle);
        tester.isGoingTo("Verify the updated title and original description are visible.");
        await Files.checkFileDescription(filesComponent, newFileTitle, fileDescription);
    });

    test("Verify adding file component to another page", async ({ tester }) => {
        tester.isGoingTo("Navigate to 9B English homepage and add a Files component.");
        await homepage.goto(188);
        tester.isGoingTo("Add Files component");
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_RESOURCES, "Files");
        filesComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        componentEnglishId = await Homepage.getComponentId(filesComponent);
        tester.isGoingTo("Attach the existing Files from 9A English → English.");
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(filesComponent);
        await files.addExistingFile(filesComponent, "English", "9A English");
        const sortedFiles = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(`Sort on 9B English after adding existing: ${sortedFiles.join(" | ")}`);
        expect(sortedFiles).toEqual([newFileTitle, "Glasses", "Where did English come from?", "Spelling tips"]);
    });

    test("Verify File component access for student", async ({ tester }) => {
        tester.isGoingTo("Impersonate student: Jana Reed");
        await tester.impersonate("Jana Reed");
        tester.isGoingTo("Open 9A English homepage and locate the Files component");
        await homepage.goto(83);
        tester.isGoingTo("Add the Files component");
        filesComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        const filesHeader = filesComponent.getByTestId("homepage-component-titlebar-name");
        const viewAllFilesBtn = filesComponent.getByTestId("view-all-files-button");
        const addFileBtn = filesComponent.getByTestId("file-select-link");
        tester.isGoingTo("Verify student permissions (header hidden, 'View All' visible, 'Add File' hidden).");
        await expect(filesHeader).toBeHidden();
        await expect(viewAllFilesBtn).toBeVisible();
        await expect(addFileBtn).toBeHidden();
    });

    test("Verify Adding Heading and Breadcrumb Visibility", async ({ tester, page }) => {
        tester.isGoingTo("Open 9A English homepage");
        await homepage.goto(83);
        tester.isGoingTo("Add Files component");
        filesComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(filesComponent);
        tester.isGoingTo("Set heading to 'Automation Heading Test' and verify breadcrumb on Resources page.");
        await Files.setHeadingAndVerifyBreadcrumb(page, filesComponent, 83, "Automation Heading Test");
        tester.isGoingTo("Return to homepage and reset heading back to default.");
        await homepage.goto(83);
        tester.isGoingTo("Add Files component locator and ID again");
        filesComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(filesComponent);
        tester.isGoingTo("Reset heading (empty → 'Files') and verify breadcrumb.");
        await Files.resetHeadingAndVerifyBreadcrumb(page, filesComponent, 83);
    });

    test.afterAll(async ({ baseTester, db, api }) => {
        baseTester.isGoingTo("clean up: remove Files component and soft-delete created file rows");
        await db.dontHaveInDatabase("file", { description: newFileTitle, deleted_at: null });
        await api.deleteHomepageComponent(188, componentEnglishId);
        await api.deleteHomepageComponent(83, componentId);
    });
});
