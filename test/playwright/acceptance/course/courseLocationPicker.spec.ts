/**
 * modify the course Faculty/Subject folder and ensure the location is saved:
 * ensure user is able to select homepage as course location and not able to select courses or units as course location,
 * ensure it is still possible to save it for course with legacy invalid(unit) location
 *
 * Steps:
 * 1. Provision sales.tar.gz data
 * 2. npx playwright test test/playwright/acceptance/course/courseLocationPicker.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CourseModify from "@schoolboxTest/playwright/support/page/courses/CourseModify";

test.describe("Course location picker tests", () => {
    test.beforeEach(async ({ tester, page, db }) => {
        const courseModify = new CourseModify(page, tester);
        tester.isGoingTo("Set the location of the ELC course to Competing ideologies unit");
        await db.updateInDatabase("folder", { parent: 266 }, { id: 65 });
        await tester.loginAsAdmin();
        tester.isGoingTo("navigate to the ELC course modify page");
        await courseModify.goto(4);
        tester.isGoingTo("click the Faculty filed to be able to select a faculty / subject folder via modal");
        await courseModify.clickSelectLocation();
    });

    /**
     * Test that user can select current (invalid) location
     */
    test("Retain current invalid location", async ({ page, tester, db }) => {
        tester.isGoingTo("check that Use This Folder button is initially disabled");
        await expect(page.locator(CourseModify.SELECTOR_USE_FOLDER)).toHaveAttribute("disabled");

        tester.isGoingTo("click current location Competing ideologies unit");
        await page.click('[id="266"]');

        tester.isGoingTo("check that Use This Folder button is now enabled");
        await expect(page.locator(CourseModify.SELECTOR_USE_FOLDER)).not.toHaveAttribute("disabled");

        tester.isGoingTo("click Use This Folder button");
        await page.click(CourseModify.SELECTOR_USE_FOLDER);

        tester.isGoingTo("click the Save Course button");
        await page.click(CourseModify.SELECTOR_SAVE_COURSE);

        tester.isGoingTo("check the course has the proper faculty / subject folder");
        await db.grabFromDatabase("course_specification", "id", { id: 4, folder_id: 65 });
        const folderId = (await db.grabFromDatabase("course_specification", "folder_id", {
            id: 4,
        })) as number;
        expect(folderId).toBe(65);

        const parentId = (await db.grabFromDatabase("folder", "parent", {
            id: 65,
        })) as number;
        expect(parentId).toBe(266);
    });

    /**
     * Tests user:
     * can see other courses
     * cannot select courses as a course location
     * cannot see units inside a course node
     * can select a homepage as a course location
     * can persist a homepage as a course location
     */

    test("Location selection", async ({ page, tester, db }) => {
        tester.isGoingTo("open the French homepage node");
        await page.click('[id="10"]');

        tester.isGoingTo("check the French homepage is selected");
        expect(page.locator('a[aria-selected="true"]')).toBeTruthy();

        tester.isGoingTo("user can see Year 9 French course node");
        await expect(page.locator('a[href="/homepage/16"]')).toContainText("Year 9 French");

        tester.isGoingTo("click Year 9 French course node");
        await page.locator('li[id="16"]').click();

        tester.isGoingTo("check Year 9 French is not selected");
        await expect(page.locator('li[id="16"]')).not.toHaveAttribute("class", "jstree-anchor jstree-clicked");

        tester.isGoingTo("check the user cannot see units inside Year 9 French course ");
        // jstree-leaf is presented when there is no child
        await expect(page.locator('li[id="16"]')).toHaveAttribute("class", "jstree-node  jstree-leaf jstree-last");

        tester.isGoingTo("check the French homepage still is selected");
        await expect(page.locator('a[id="10_anchor"]')).toHaveAttribute("class", "jstree-anchor jstree-clicked");

        tester.isGoingTo("click English homepage");
        await page.click('[id="85"]');

        tester.isGoingTo("click Use This Folder button");
        await page.click(CourseModify.SELECTOR_USE_FOLDER);

        tester.isGoingTo("check the form has updated with this ID");
        await expect(page.locator("[data-course-parent-selector]")).toContainText("English");

        tester.isGoingTo("lick Save Course button");
        await page.click(CourseModify.SELECTOR_SAVE_COURSE);

        tester.isGoingTo("check the database is updated to reflect the new course location");
        await db.grabFromDatabase("folder", "id", { id: 65, parent: 85 });
    });

    /**
     * updating data to its original value
     */
    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does impact next suite of tests",
        );
        await db.updateInDatabase("folder", { parent: 2 }, { id: 65 });
    });
});
