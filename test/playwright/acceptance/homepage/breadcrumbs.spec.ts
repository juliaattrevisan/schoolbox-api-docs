import type { Locator } from "@playwright/test";
import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

test.describe("Breadcrumbs Component Tests", () => {
    // Run tests serially, as tests rely on prior tests to have been run
    test.describe.configure({ mode: "serial" });

    let breadcrumb9AEnglishId: number;
    let breadcrumb9BEnglishId: number;
    let breadcrumb9AFrenchId: number;
    let homepage: Homepage;
    let breadcrumbsComponent: Locator;

    test.beforeEach(async ({ tester, page }) => {
        homepage = new Homepage(tester, page);
        await tester.loginAsAdmin();
    });

    test("Verify Add Breadcrumbs", async ({ tester, page }) => {
        tester.isGoingTo("Homepage, open tab panel, click components tab");
        await homepage.goto(83);
        tester.isGoingTo("Add Breadcrumbs component");
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_RESOURCES, "Breadcrumbs");
        breadcrumbsComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        breadcrumb9AEnglishId = await Homepage.getComponentId(breadcrumbsComponent);
        tester.isGoingTo(`Use component: ${breadcrumb9AEnglishId}`);
        const breadcrumb = page.locator("ul.breadcrumb");
        await expect(breadcrumb.locator('a:text-is("Classes")')).toHaveAttribute("href", "/homepage/263/");
        await expect(breadcrumb.locator('a:text-is("2019 Classes")')).toHaveAttribute("href", "/homepage/51/");
        await expect(breadcrumb.locator('a:text-is("Middle School")')).toHaveAttribute("href", "/homepage/100/");
        await expect(breadcrumb.locator('a:text-is("Year 9")')).toHaveAttribute("href", "/homepage/198/");
        await expect(breadcrumb.locator('a:text-is("English")')).toHaveAttribute("href", "/homepage/199/");
        await expect(breadcrumb.locator('a:text-is("9A English")')).toHaveAttribute("href", "/homepage/83/");

        await homepage.goto(188);
        tester.isGoingTo("Add Breadcrumbs component");
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_RESOURCES, "Breadcrumbs");
        breadcrumbsComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        breadcrumb9BEnglishId = await Homepage.getComponentId(breadcrumbsComponent);
        tester.isGoingTo(`Use component: ${breadcrumb9BEnglishId}`);
        await expect(breadcrumb).toBeVisible();
        await expect(breadcrumb.locator('a:text-is("Classes")')).toHaveAttribute("href", "/homepage/263/");
        await expect(breadcrumb.locator('a:text-is("2019 Classes")')).toHaveAttribute("href", "/homepage/51/");
        await expect(breadcrumb.locator('a:text-is("Middle School")')).toHaveAttribute("href", "/homepage/100/");
        await expect(breadcrumb.locator('a:text-is("Year 9")')).toHaveAttribute("href", "/homepage/198/");
        await expect(breadcrumb.locator('a:text-is("English")')).toHaveAttribute("href", "/homepage/199/");
        await expect(breadcrumb.locator('a:text-is("9B English")')).toHaveAttribute("href", "/homepage/188/");

        await homepage.goto(52);
        tester.isGoingTo("Add Breadcrumbs component");
        await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_RESOURCES, "Breadcrumbs");
        breadcrumbsComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        breadcrumb9AFrenchId = await Homepage.getComponentId(breadcrumbsComponent);
        tester.isGoingTo(`Use component: ${breadcrumb9AFrenchId}`);
        await expect(breadcrumb).toBeVisible();
        await expect(breadcrumb.locator('a:text-is("Classes")')).toHaveAttribute("href", "/homepage/263/");
        await expect(breadcrumb.locator('a:text-is("2019 Classes")')).toHaveAttribute("href", "/homepage/51/");
        await expect(breadcrumb.locator('a:text-is("Middle School")')).toHaveAttribute("href", "/homepage/100/");
        await expect(breadcrumb.locator('a:text-is("Year 9")')).toHaveAttribute("href", "/homepage/198/");
        await expect(breadcrumb.locator('a:text-is("French")')).toHaveAttribute("href", "/homepage/200/");
        await expect(breadcrumb.locator('a:text-is("9A French")')).toHaveAttribute("href", "/homepage/52/");
    });

    test("Configure the breadcrumb to not show title to read only users", async ({ tester, page }) => {
        tester.isGoingTo("Homepage, open tab panel, click components tab");
        await homepage.goto(83);
        tester.isGoingTo("Use existing Breadcrumbs component from previous test");
        breadcrumbsComponent = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
        breadcrumb9AEnglishId = await Homepage.getComponentId(breadcrumbsComponent);
        tester.isGoingTo(`Use component: ${breadcrumb9AEnglishId}`);
        const breadcrumb = page.locator("ul.breadcrumb");
        await expect(breadcrumb.locator('a:text-is("Classes")')).toHaveAttribute("href", "/homepage/263/");
        await expect(breadcrumb.locator('a:text-is("2019 Classes")')).toHaveAttribute("href", "/homepage/51/");
        await expect(breadcrumb.locator('a:text-is("Middle School")')).toHaveAttribute("href", "/homepage/100/");
        await expect(breadcrumb.locator('a:text-is("Year 9")')).toHaveAttribute("href", "/homepage/198/");
        await expect(breadcrumb.locator('a:text-is("English")')).toHaveAttribute("href", "/homepage/199/");
        await expect(breadcrumb.locator('a:text-is("9A English")')).toHaveAttribute("href", "/homepage/83/");
        await homepage.clickComponentsTab(Homepage.ELEMENT_LOCK_TOGGLE);
        await Homepage.clickComponentSettings(breadcrumbsComponent);
        tester.isGoingTo("Update Breadcrumbs settings (hide title for read-only users)");
        tester.isGoingTo("Uncheck Show to Read Only Users");
        await page.locator(`#component${breadcrumb9AEnglishId} input[type="submit"][value="Update"]`).click();
        tester.isGoingTo("Confirm title bar is visible for Alress");
        const breadcrumbHeader = breadcrumbsComponent.getByTestId("homepage-component-titlebar-name");
        await expect(breadcrumbHeader).toBeVisible();

        await tester.impersonate("Jana Reed");
        tester.isGoingTo("As student (Jana), verify breadcrumb rules on 9A English");
        await homepage.goto(83);
        const breadcrumbStudent = page.locator("ul.breadcrumb");
        const classes = breadcrumbStudent.locator('a:has-text("Classes")');
        tester.isGoingTo('Ensure "Classes" is visible but not linking to /homepage/263/');
        await expect(breadcrumbStudent).toBeVisible();
        await expect(classes).toBeVisible();
        await expect(classes).not.toHaveAttribute("href", "/homepage/263/");
        await expect(breadcrumbStudent.locator('a:text-is("2019 Classes")')).toHaveAttribute("href", "/homepage/51/");
        await expect(breadcrumbStudent.locator('a:text-is("Middle School")')).toHaveAttribute("href", "/homepage/100/");
        await expect(breadcrumbStudent.locator('a:text-is("Year 9")')).toHaveAttribute("href", "/homepage/198/");
        await expect(breadcrumbStudent.locator('a:text-is("English")')).toHaveAttribute("href", "/homepage/199/");
        await expect(breadcrumbStudent.locator('a:text-is("9A English")')).toHaveAttribute("href", "/homepage/83/");
        const breadcrumbHeaderStudent = breadcrumbsComponent.getByTestId("homepage-component-titlebar-name");
        await expect(breadcrumbHeaderStudent).toBeHidden();
    });

    test.afterAll(async ({ baseTester, api }) => {
        baseTester.isGoingTo("clean up: remove Breadcrumbs components");

        await api.deleteHomepageComponent(188, breadcrumb9BEnglishId);
        await api.deleteHomepageComponent(83, breadcrumb9AEnglishId);
        await api.deleteHomepageComponent(52, breadcrumb9AFrenchId);
    });
});
