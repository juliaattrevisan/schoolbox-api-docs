import type { Locator } from "@playwright/test";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

test.describe("Breadcrumbs Component Tests", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });

    let breadcrumb9AEnglishId: number;
    let breadcrumb9BEnglishId: number;
    let breadcrumb9AFrenchId: number;
    let breadcrumbsComponent: Locator;
    let homepage: Homepage;

    test.beforeEach(async ({ tester, page }) => {
        homepage = new Homepage(tester, page);
        await tester.loginAsAdmin();
    });

    test("Verify Add Breadcrumbs", async ({ tester, page }) => {
        tester.isGoingTo("Homepage, open tab panel, click components tab");
        await homepage.goto(83);
        tester.isGoingTo("Add Curriculum Overview component");
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_LEARNING, "Curriculum Description);
        breadcrumbsComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        breadcrumb9AEnglishId = await Homepage.getComponentId(breadcrumbsComponent);
        tester.isGoingTo(`Use component: ${breadcrumb9AEnglishId}`);
        await breadcrumbsComponent.locator('a[href="#map-curriculum-codes"]').click();
        
        // const breadcrumb = page.locator("ul.breadcrumb");
        // await expect(breadcrumb.locator('a:text-is("Classes")')).toHaveAttribute("href", "/homepage/263/");
        // await expect(breadcrumb.locator('a:text-is("2019 Classes")')).toHaveAttribute("href", "/homepage/51/");
        // await expect(breadcrumb.locator('a:text-is("Middle School")')).toHaveAttribute("href", "/homepage/100/");
        // await expect(breadcrumb.locator('a:text-is("Year 9")')).toHaveAttribute("href", "/homepage/198/");
        // await expect(breadcrumb.locator('a:text-is("English")')).toHaveAttribute("href", "/homepage/199/");
        // await expect(breadcrumb.locator('a:text-is("9A English")')).toHaveAttribute("href", "/homepage/83/");

    });


    test.afterAll(async ({ baseTester, api }) => {
        baseTester.isGoingTo("clean up: remove Breadcrumbs components");
    });
});
