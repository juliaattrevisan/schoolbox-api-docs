import { expect, test } from "@schoolboxTest/playwright/support/integrations";
import ClassTeachersComponent from "@schoolboxTest/playwright/support/page/homepage/ClassTeachersComponent";
import Common from "@schoolboxTest/playwright/support/page/Common";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

/**
 * Check the Class Teachers component on the 9A English homepage as a teacher (Jack Coles)
 * Check the teacher name, pic and meta are presented correctly as per provision data
 * Check the student can view the class teacher component
 *
 * 1. Provision sales.tar.gz data
 * 2. npx playwright test classTeachersComponent.spec.ts
 */

test.describe.serial("Class Teachers Component - Homepage", () => {
    let classTeachersComponent: ClassTeachersComponent;
    let homepage: Homepage;

    const classId = Common.CLASS_ID_9AENG; // 9A English, class id 83
    const teacherJackId = Common.USER_ID_JACKCOLES; // Jack Coles, user id 22

    test.beforeEach(async ({ tester, page }) => {
        classTeachersComponent = new ClassTeachersComponent(page, tester);
        homepage = new Homepage(tester, page);
        await tester.loginAsAdmin();
        await tester.impersonateTeacher();
    });

    test("add the class teachers component to the 9A English homepage", async ({ tester, api }) => {
        try {
            tester.isGoingTo("navigate to the 9A English homepage");
            await homepage.goto(classId);

            tester.isGoingTo("add the Class Teachers component from the Learning section");
            await homepage.addComponent(Homepage.ELEMENT_ADD_COMPONENT_SECTION_LEARNING, "Class Teachers");

            tester.isGoingTo("check the Class Teachers component is present on the page");
            await expect(classTeachersComponent.locateComponent()).toBeVisible();

            tester.isGoingTo(
                "check Jack Coles is presented in the Class Teachers component with his name, photo and meta",
            );
            await classTeachersComponent.checkTeacherPresented(teacherJackId, Common.USER_JACKCOLES);

            tester.isGoingTo("check the Class Teachers component is presented for students");
            await tester.impersonateViaUserProfilePage(Common.USER_JANAREED);
            await homepage.goto(classId);
            await expect(classTeachersComponent.locateComponent()).toBeVisible();
        } finally {
            const componentLocation = homepage.getComponent(Homepage.ELEMENT_COMPONENT_LAYOUT_TOP, 1);
            const classTeachersComponentId = await Homepage.getComponentId(componentLocation);
            await api.deleteHomepageComponent(83, classTeachersComponentId);
        }
    });
});
