/**
 * This test checks if the link components are presented under the homepage
 */
import type { Locator } from "@playwright/test";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import Links from "@schoolboxTest/playwright/support/page/homepage/Links";
import Files from "@schoolboxTest/playwright/support/page/homepage/Files";

test.describe("Files Component Tests", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });

    let componentId: number;
    let componentEnglishId: number;
    let homepage: Homepage;
    let files: Files;
    let filesComponent: Locator;
    let linkTitle: string = "Test Link Automation";
    const newLinkTitle: string = "Test Link Automation Updated";
    const linkUrl: string = "https://www.youtube.com";
    const linkDsc: string = "Description here";

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
        tester.isGoingTo("Add File");
        await Files.selectFile(page, filesComponent, "Test Automation File", "Description for File", false);
        tester.isGoingTo("Verify File added Translucent Title ");
        await Files.verifyFileTitle(filesComponent, "Test Automation File", false);
    });

    test("Verify Sorting Image Files", async ({ tester, page }) => {
        tester.isGoingTo("Navigate to 9A English homepage and configure the Files component");
        await homepage.goto(83);
        filesComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        componentId = await Homepage.getComponentId(filesComponent);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(filesComponent);
        tester.isGoingTo("Verify sorting files by Image files");
        await Files.sortImageFiles(filesComponent);
        let sortedImageFiles: string[] = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(sortedImageFiles.toString());
        expect(sortedImageFiles).toEqual(["Test Automation File", "Glasses"]);
    });

    test("Verify Sorting Document and Video Files", async ({ tester, page }) => {
        tester.isGoingTo("Navigate to 9A English homepage and configure the Files component");
        await homepage.goto(83);
        filesComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        componentId = await Homepage.getComponentId(filesComponent);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(filesComponent);
        tester.isGoingTo("Verify sorting files by Document and Videofiles");
        await Files.sortVideoAndDocumentFiles(filesComponent);
        let sortedDocumentAndVideoFiles: string[] = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(sortedDocumentAndVideoFiles.toString());
        expect(sortedDocumentAndVideoFiles).toEqual(["Where did English come from?", "Spelling tips"]);

        await Homepage.clickComponentSettings(filesComponent);
        await Files.sortFiles(filesComponent, "Name", "asc");
        let sortedFilesByNameAsc: string[] = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(sortedFilesByNameAsc.toString());
        expect(sortedFilesByNameAsc).toEqual([
            "Glasses",
            "Spelling tips",
            "Test Automation File",
            "Where did English come from?",
        ]);

        await Homepage.clickComponentSettings(filesComponent);
        await Files.sortFilesDesc(filesComponent, "Name", "desc");
        let sortedFilesByNameDesc: string[] = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(sortedFilesByNameDesc.toString());
        expect(sortedFilesByNameDesc).toEqual([
            "Where did English come from?",
            "Test Automation File",
            "Spelling tips",
            "Glasses",
        ]);

        await Homepage.clickComponentSettings(filesComponent);
        await Files.sortFilesDesc(filesComponent, "Date", "asc");
        let sortedFilesByDateAsc: string[] = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(sortedFilesByDateAsc.toString());
        expect(sortedFilesByDateAsc).toEqual([
            "Spelling tips",
            "Where did English come from?",
            "Glasses",
            "Test Automation File",
        ]);

        await Homepage.clickComponentSettings(filesComponent);
        await Files.sortFilesDesc(filesComponent, "Date", "desc");
        let sortedFilesByDateDesc: string[] = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(sortedFilesByDateDesc.toString());
        expect(sortedFilesByDateDesc).toEqual([
            "Test Automation File",
            "Glasses",
            "Where did English come from?",
            "Spelling tips",
        ]);

        await Homepage.clickComponentSettings(filesComponent);
        await Files.setFileLimit(filesComponent, "2");
        let setLimit: string[] = await Files.getAllFiles(filesComponent);
        tester.isGoingTo(setLimit.toString());
        expect(setLimit).toEqual(["Test Automation File", "Glasses"]);

        await Homepage.clickComponentSettings(filesComponent);
        await Files.updateFileComponentSetting(filesComponent);
        await Files.checkFileDescription(filesComponent, "Test Automation File", "Description for File");
    });
});
