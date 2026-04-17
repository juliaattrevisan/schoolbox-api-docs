import { test } from "@schoolboxTest/playwright/support/integrations";
import ClassListComponent from "@schoolboxTest/playwright/support/page/homepage/ClassListComponent";
import Common from "@schoolboxTest/playwright/support/page/Common";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

/**
 * Check the Class List component on the 9A English homepage as a teacher (Jack Coles)
 * Check the student name, pic and meta are presented correctly as per provision data
 *
 * 1. Provision sales.tar.gz data
 * 2. npx playwright test classListComponent.spec.ts
 */

test.describe.serial("Class List Component - Homepage", () => {
    let classListComponent: ClassListComponent;
    let homepage: Homepage;

    const classId = Common.CLASS_ID_9AENG; // 9A English, class id 83
    const studentJanaId = Common.USER_ID_JANAREED; // Jana Reed, user id 25
    const studentJamalId = Common.USER_ID_JAMALALI; // Jamal Ali, user id 60

    test.beforeEach(async ({ tester, page }) => {
        classListComponent = new ClassListComponent(page, tester);
        homepage = new Homepage(tester, page);
        await tester.loginAsAdmin();
        await tester.impersonateTeacher();
    });

    test("check the email buttons are presented properly in the class list component", async ({ tester }) => {
        tester.isGoingTo("navigate to the 9A English homepage");
        await homepage.goto(classId);

        tester.isGoingTo("check the Email Students and Email Parents buttons are visible with correct text");
        await classListComponent.checkEmailButtons();
    });

    test("check Jana and Jamal are presented in the class list component with their image and meta", async ({
        tester,
    }) => {
        tester.isGoingTo("navigate to the 9A English homepage");
        await homepage.goto(classId);

        tester.isGoingTo("check Jana Reed is presented in the class list component with her name, photo and meta");
        await classListComponent.checkStudentPresented(studentJanaId, Common.USER_JANAREED);

        tester.isGoingTo("check Jamal Ali is presented in the class list component with his name, photo and meta");
        await classListComponent.checkStudentPresented(studentJamalId, Common.USER_JAMALALI);
    });

    test("check the student can see the class list component but not the email buttons", async ({ tester }) => {
        tester.isGoingTo("impersonate student");
        await tester.impersonateViaUserProfilePage(Common.USER_JANAREED);

        tester.isGoingTo("navigate to the 9A English homepage");
        await homepage.goto(classId);

        tester.isGoingTo("check the Email Students and Email Parents buttons are not visible for students");
        await classListComponent.checkEmailButtonsNotVisible();
    });
});
