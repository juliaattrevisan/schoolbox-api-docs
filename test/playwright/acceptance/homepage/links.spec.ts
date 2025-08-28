/**
 * This test checks if the link components are presented under the homepage
 */
import type { Locator } from "@playwright/test";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";
import Links from "@schoolboxTest/playwright/support/page/homepage/Links";

test.describe("Links Component Tests", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });

    let componentId: number;
    let homepage: Homepage;
    let links: Links;
    let linksComponent: Locator;
    let linkTitle: string = "Test Link Automation";
    const newLinkTitle: string = "Test Link Automation Updated";
    const linkUrl: string = "https://www.youtube.com";
    const linkDsc: string = "Description here";

    test.beforeEach(async ({ tester, page }) => {
        homepage = new Homepage(tester, page);
        links = new Links(tester, page);

        await tester.loginAsAdmin();
    });

    test("Verify Add Link", async ({ tester, page }) => {
        tester.isGoingTo("Homepage, open tab panel, click components tab and verify component Icon");
        await homepage.goto(83);
        await homepage.openTabPanel(Homepage.ELEMENT_ADD_COMPONENT_TAB);
        await homepage.clickComponentsTab(Homepage.ELEMENT_COMPONENT_SECTION_RESOURCES);
        await homepage.checkComponentIcon("Component_Homepage_LinkListController");
        tester.isGoingTo("Add Links component");
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_RESOURCES, "Links");
        linksComponent = page.locator(Homepage.locateComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1));
        const componentIdStr = await linksComponent.getAttribute("id");
        componentId = Number(componentIdStr?.match(/\d+/)?.[0]);

        tester.isGoingTo(`Use component: ${componentId}`);
        await homepage.seeInComponentHeading("Links", Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        tester.isGoingTo("Check the two links exists ");
        await Links.verifyLink(linksComponent, "Where did English come from?", false);
        await Links.verifyLink(linksComponent, "Plain English Romeo and Juliet", false);
        tester.isGoingTo("Add Title");
        await links.addLink(linksComponent, linkTitle, linkUrl, linkDsc, false);
        tester.isGoingTo("Verify added Translucent Title ");
        await Links.verifyLink(linksComponent, linkTitle, true);
    });

    test("Verify Sorting Links", async ({ tester, page }) => {
        tester.isGoingTo("Navigate to 9A English homepage and configure the Links component");
        await homepage.goto(83);
        linksComponent = page.locator(Homepage.locateComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1));
        const componentIdStr = await linksComponent.getAttribute("id");
        componentId = Number(componentIdStr?.match(/\d+/)?.[0]);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(linksComponent);
        tester.isGoingTo("Verify sorting links by Name and Date asc and desc");
        await Links.sortLinks(linksComponent, "Name", "asc");
        let sortedLinks: string[] = await Links.getAllLinks(linksComponent);
        tester.isGoingTo(sortedLinks.toString());
        expect(sortedLinks).toEqual(["Plain English Romeo and Juliet", linkTitle, "Where did English come from?"]);

        tester.isGoingTo("Sort links by Name and Date desc");
        await Homepage.clickComponentSettings(linksComponent);
        await Links.sortLinks(linksComponent, "Name", "desc");
        sortedLinks = await Links.getAllLinks(linksComponent);
        tester.isGoingTo(sortedLinks.toString());
        expect(sortedLinks).toEqual(["Where did English come from?", linkTitle, "Plain English Romeo and Juliet"]);

        tester.isGoingTo("Sort links by Date asc");
        await Homepage.clickComponentSettings(linksComponent);
        await Links.sortLinks(linksComponent, "Date", "asc");
        sortedLinks = await Links.getAllLinks(linksComponent);
        tester.isGoingTo(sortedLinks.toString());
        expect(sortedLinks).toEqual(["Plain English Romeo and Juliet", "Where did English come from?", linkTitle]);

        tester.isGoingTo("Sort links by Date desc");
        await Homepage.clickComponentSettings(linksComponent);
        await Links.sortLinks(linksComponent, "Date", "desc");
        sortedLinks = await Links.getAllLinks(linksComponent);
        tester.isGoingTo(sortedLinks.toString());
        expect(sortedLinks).toEqual([linkTitle, "Where did English come from?", "Plain English Romeo and Juliet"]);

        tester.isGoingTo("Set link limit to 2");
        await Homepage.clickComponentSettings(linksComponent);
        await Links.setLinkLimit(linksComponent, "2");
        sortedLinks = await Links.getAllLinks(linksComponent);
        tester.isGoingTo(sortedLinks.toString());
        expect(sortedLinks).toEqual([linkTitle, "Where did English come from?"]);

        tester.isGoingTo("click view all links and verify navigation to resource page");
        await linksComponent.getByText("View All Links").click();
        await expect(page).toHaveURL(/.*\/resources$/);
    });

    test("Verify Update link", async ({ tester, page, db }) => {
        const linkId = (await db.grabFromDatabase("link", "id", {
            deleted_at: null,
            description: "Test Link Automation",
        })) as number;

        tester.isGoingTo("Navigate to 9A English homepage and update a link in the Links component");
        await homepage.goto(83);
        tester.isGoingTo("Modify Link Title");
        linksComponent = page.locator(Homepage.locateComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1));
        const componentIdStr = await linksComponent.getAttribute("id");
        componentId = Number(componentIdStr?.match(/\d+/)?.[0]);
        await links.modifyLink(linksComponent, linkId, newLinkTitle);
        tester.isGoingTo("Update link setting");
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(linksComponent);
        await Links.updateLinkComponentSetting(linksComponent, "All", componentId, false);
        tester.isGoingTo("Verify new Title and description");
        await Links.checkDescription(linksComponent, newLinkTitle, linkDsc);
        tester.isGoingTo("Verfiy existing link title and description");
        await Links.checkDescription(
            linksComponent,
            "Plain English Romeo and Juliet",
            "This link displays the original text (in Shakespearean English) next to a plain English translation. Use this to help you understand the text.",
        );
        linkTitle = newLinkTitle;
    });

    test("Verify Link component access for student", async ({ tester, page }) => {
        tester.isGoingTo("Navigate to 9A English homepage and update a link in the Links component");
        await homepage.goto(83);

        linksComponent = page.locator(Homepage.locateComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1));
        const componentIdStr = await linksComponent.getAttribute("id");
        componentId = Number(componentIdStr?.match(/\d+/)?.[0]);
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(linksComponent);
        tester.isGoingTo("Update the component limit to 2 and un-tick the title to read only");
        await Links.updateLinkComponentSetting(linksComponent, "2", componentId, true);
        tester.isGoingTo("Login as student and check the header and buttons are not visible");
        await tester.impersonate("Jana Reed");
        await homepage.goto(83);
        const linksHeader = linksComponent.getByTestId("homepage-component-titlebar-name");
        const viewAllLinksBtn = linksComponent.getByText("View All Links");
        const addLinkBtn = linksComponent.getByText("Add Link");
        await expect(linksHeader).toBeHidden();
        await expect(viewAllLinksBtn).toBeHidden();
        await expect(addLinkBtn).toBeHidden();
    });

    // test("Verify adding link component to another page", async ({ tester, page }) => {
    //     tester.isGoingTo("Navigate to 3RW homepage and alt-source the Links component");
    //     await homepage.goto(188);
    //     await homepage.openTabPanel(Homepage.ELEMENT_ADD_COMPONENT_TAB);
    //     await homepage.clickComponentsTab(Homepage.ELEMENT_COMPONENT_SECTION_RESOURCES);
    //     await homepage.checkComponentIcon("Component_Homepage_LinkListController");
    //     tester.isGoingTo("Add Links component");
    //     await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_RESOURCES, "Links");
    //     linksComponent = page.locator(Homepage.locateComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1));
    //     const componentIdStr = await linksComponent.getAttribute("id");
    //     componentId = Number(componentIdStr?.match(/\d+/)?.[0]);

    //     await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
    //     await homepage.clickComponentSettings(linksComponent);
    //     await links.addExistingLink(linksComponent, "English", "9A English");
    //     const sortedLinks = await Links.getAllLinks(linksComponent);
    //     tester.isGoingTo(sortedLinks.toString());
    //     expect(sortedLinks).toEqual([newLinkTitle, "Where did English come from?", "Plain English Romeo and Juliet"]);
    // });

    test.afterAll(async ({ baseTester, db, api }) => {
        baseTester.isGoingTo("clean up: remove Links component and soft-delete created link rows");
        await api.deleteHomepageComponent(83, componentId);
        await db.dontHaveInDatabase("link", { url: linkUrl });
        // await api.deleteHomepageComponent(188, componentId);
    });
});
