/**
 * This test checks if the link components are presented under the homepage
 */
import type { Locator } from "@playwright/test";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import Links from "@schoolboxTest/playwright/support/page/homepage/Links";
import Files from "@schoolboxTest/playwright/support/page/homepage/Files";

test.describe("Links Component Tests", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });

    let componentId: number;
    let componentEnglishId: number;
    let homepage: Homepage;
    let links: Links;
    let filesComponent: Locator;
    let linkTitle: string = "Test Link Automation";
    const newLinkTitle: string = "Test Link Automation Updated";
    const linkUrl: string = "https://www.youtube.com";
    const linkDsc: string = "Description here";

    test.beforeEach(async ({ tester, page }) => {
        homepage = new Homepage(tester, page);
        links = new Links(tester, page);

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
});
