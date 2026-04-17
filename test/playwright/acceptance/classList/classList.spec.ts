import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import ClassList from "@schoolboxTest/playwright/support/page/homepage/pages/ClassList";
import Common from "@schoolboxTest/playwright/support/page/Common";
import LearningMomentShare from "@schoolboxTest/playwright/support/page/learningMoments/LearningMomentShare";
import LearningMomentModify from "@schoolboxTest/playwright/support/page/learningMoments/LearningMomentModify";
import Badges from "@schoolboxTest/playwright/support/page/badges/Badges";
import CreatePastoralRecord from "@schoolboxTest/playwright/support/page/pastoralCare/CreatePastoralRecord";
import PastoralProfile from "@schoolboxTest/playwright/support/page/pastoralCare/PastoralProfile";

/**
 * Check data for Jana Reed for all the columns in the 9A English class list as per provision data
 * Check the buttons and columns are properly presented and follows the terminology terms
 * Check that the buttons and 3-dot menu options redirects to the correct pages with the proper information for the students when selected
 * Check that by clicking the return button or completing actions it redirects back to the class list page
 *
 * 1. Provision sales.tar.gz data
 * 2. npx playwright test classList.spec.ts
 */

test.describe.serial("Class Members - Class List", () => {
    let classList: ClassList;
    let learningMomentShare: LearningMomentShare;
    let learningMomentModify: LearningMomentModify;
    let common: Common;
    let badges: Badges;
    let createPastoralRecord: CreatePastoralRecord;
    let pastoralProfile: PastoralProfile;

    const classId = Common.CLASS_ID_9AENG; // 9A English, class id 83
    const studentJanaId = Common.USER_ID_JANAREED; // Jana Reed, user id 25
    const studentJamalId = Common.USER_ID_JAMALALI; // Jamal Ali, user id 60
    const studentExternalId = "507"; // Jana Reed's synergy_id in the sales provision data

    test.beforeEach(async ({ tester, page }) => {
        classList = new ClassList(page, tester);
        learningMomentShare = new LearningMomentShare(tester, page);
        learningMomentModify = new LearningMomentModify(tester, page);
        badges = new Badges(tester, page);
        common = new Common(page);
        createPastoralRecord = new CreatePastoralRecord(page);
        pastoralProfile = new PastoralProfile(page, tester);
        await tester.loginAsAdmin();
    });

    test("check all columns for Jana Reed in the 9A English class list", async ({ tester }) => {
        tester.isGoingTo("navigate to the 9A English class list page");
        await classList.goto(classId);

        tester.isGoingTo("check all columns for Jana Reed match expected values");
        await classList.checkStudentRow(studentJanaId, "Reed, Jana", studentExternalId, "9", "Prospect", true);
    });

    test("check by clicking the buttons actions it redirects to the correct page with the proper information", async ({
        tester,
        page,
        db,
    }) => {
        try {
            tester.isGoingTo("navigate to the 9A English class list page");
            await tester.impersonateTeacher();
            await classList.goto(classId);

            tester.isGoingTo(
                "share a learning moment for Jana and Jamal and check it redirects back to the class list",
            );
            await classList.performActionForSelectedStudents([studentJanaId, studentJamalId], "share learning moment");
            await learningMomentShare.clickShareText();
            await common.checkMetaStudentNames(["Ms Jana Reed", "Jamal Ali"], ["Ms Penelope Li", "Louisa Smith"]);
            await learningMomentModify.typeBody("This is a learning moment for Jana Reed and Jamal Ali");
            await learningMomentModify.clickPublish();
            await classList.expectClassListPage(classId);

            tester.isGoingTo(
                "click set goals, check the proper students are presented at the top of the page, click return and check it is back to the class list",
            );
            await classList.performActionForSelectedStudents([studentJanaId, studentJamalId], "set goals");
            await common.checkMetaStudentNames(["Ms Jana Reed", "Jamal Ali"], ["Ms Penelope Li", "Louisa Smith"]);
            await badges.clickReturnButton();
            await classList.expectClassListPage(classId);

            tester.isGoingTo(
                "click award achievement, check the proper students are presented at the top of the page, click return and check it is back to the class list",
            );
            await classList.performActionForSelectedStudents([studentJanaId, studentJamalId], "award achievement");
            await common.checkMetaStudentNames(["Ms Jana Reed", "Jamal Ali"], ["Ms Penelope Li", "Louisa Smith"]);
            await badges.clickReturnButton();
            await classList.expectClassListPage(classId);

            tester.isGoingTo(
                "click add pastoral record, check the proper students are presented at the top of the page, create the pastoral record and check it is back to the class list",
            );
            await classList.performActionForSelectedStudents([studentJanaId, studentJamalId], "add pastoral record");
            await common.checkSelectizeInput(["Ms Jana Reed", "Jamal Ali"], ["Ms Penelope Li", "Louisa Smith"]);
            await createPastoralRecord.typeTitle("This is a pastoral record for Jana Reed and Jamal Ali");
            await createPastoralRecord.selectType("1");
            await createPastoralRecord.clickSave();
            await classList.expectClassListPage(classId);
            tester.isGoingTo("check the pastoral record is created for the students.");
            await pastoralProfile.goto(studentJanaId);
            await expect(page.locator("#pastoralRecords")).toContainText(
                "This is a pastoral record for Jana Reed and Jamal Ali",
            );
            await pastoralProfile.goto(studentJamalId);
            await expect(page.locator("#pastoralRecords")).toContainText(
                "This is a pastoral record for Jana Reed and Jamal Ali",
            );

            tester.isGoingTo(
                "click compose email, check the proper students are at the modal, click compose email and check it redirects to the compose email page",
            );
            await classList.goto(classId);
            await classList.performActionForSelectedStudents([studentJanaId, studentJamalId], "compose email");
            await common.waitForModalToBeOpen();
            const composeEmailModal = page.locator("#classList-email");
            await expect(composeEmailModal.locator("ul.meta")).toContainText("Jana Reed");
            await expect(composeEmailModal.locator("ul.meta")).toContainText("Jamal Ali");
            await expect(composeEmailModal.locator("ul.meta")).not.toContainText("Penelope Li");
            await composeEmailModal.locator("button:has-text('Compose Email')").click();
            await expect(page).toHaveURL(/\/mail\/create/);
        } finally {
            tester.isGoingTo("clean up the created learning moment, pastoral record and badge");
            await db.updateInDatabase(
                "moment",
                { deleted_at: "2022-01-15 11:22:34" },
                { "body LIKE": "%This is a learning moment for Jana Reed and Jamal Ali%", deleted_at: null },
            );
            await db.updateInDatabase(
                "pastoral",
                { deleted_at: "2022-01-15 11:22:34" },
                { "heading LIKE": "%This is a pastoral record for Jana Reed and Jamal Ali%", deleted_at: null },
            );
        }
    });

    test("check the student 3-dot options and if it redirects to the correct page", async ({ tester }) => {
        tester.isGoingTo("navigate to the 9A English class list page and check the students 3-dot menu options");
        await tester.impersonateTeacher();
        await classList.goto(classId);
        await classList.check3DotMenuOptions(studentJanaId, [
            "Due Work",
            "Grades",
            "ePortfolio",
            "Pastoral Care",
            "Calendar",
            "Timetable",
        ]);
        await classList.check3DotMenuOptions(
            studentJamalId,
            ["Due Work", "Grades", "Pastoral Care", "Calendar", "Timetable"],
            ["ePortfolio"],
        );

        tester.isGoingTo("click the 3-dot options for Jana and check the options and redirections");
        await classList.check3DotMenuOptionRedirect(
            classId,
            studentJanaId,
            "Due Work",
            new RegExp(`learning/due/${studentJanaId}`),
        );
        await classList.check3DotMenuOptionRedirect(
            classId,
            studentJanaId,
            "Grades",
            new RegExp(`learning/grades/${studentJanaId}`),
        );
        await classList.check3DotMenuOptionRedirect(
            classId,
            studentJanaId,
            "ePortfolio",
            new RegExp(`eportfolio/${studentJanaId}/profile`),
        );
        await classList.check3DotMenuOptionRedirect(
            classId,
            studentJanaId,
            "Pastoral Care",
            new RegExp(`pastoral/student/${studentJanaId}`),
        );
        await classList.check3DotMenuOptionRedirect(classId, studentJanaId, "Calendar", /calendar\//);
        await classList.check3DotMenuOptionRedirect(
            classId,
            studentJanaId,
            "Timetable",
            new RegExp(`timetable/${studentJanaId}`),
        );
    });
    test("check the localisation reflects in the class list page", async ({ tester, db }) => {
        try {
            tester.isGoingTo("update the terminology via database to check if it reflects in the class list page");
            await db.updateInDatabase(
                "localisation",
                { singular_form: "pastoral care test" },
                { term: "pastoralCare" },
            );
            await db.updateInDatabase("localisation", { singular_form: "year level test" }, { term: "yearLevel" });
            await db.updateInDatabase("localisation", { singular_form: "due work test" }, { term: "dueWork" });
            await db.updateInDatabase("localisation", { plural_form: "grades tests" }, { term: "grades" });
            await db.updateInDatabase("localisation", { singular_form: "house test" }, { term: "schoolHouse" });

            tester.isGoingTo("navigate to the 9A English class list page and check the terminology terms are shown");
            await tester.impersonateTeacher();
            await classList.goto(classId);
            await classList.checkAddPastoralRecordButtonText("Add Pastoral Care Test Record");
            await classList.check3DotMenuOptionsText(studentJanaId, {
                "Due Work Test": "Due Work Test",
                "Grades Tests": "Grades Tests",
                "Pastoral Care Test": "Pastoral Care Test",
            });
            await classList.checkColumnHeaders("Year Level Test", "House Test");

            tester.isGoingTo(
                "update the capitalised first to 0 terminology via database to check if it reflects in the class list page",
            );
            await db.updateInDatabase("localisation", { capitalise_first: 0 }, { term: "pastoralCare" });
            await db.updateInDatabase("localisation", { capitalise_first: 0 }, { term: "yearLevel" });
            await db.updateInDatabase("localisation", { capitalise_first: 0 }, { term: "dueWork" });
            await db.updateInDatabase("localisation", { capitalise_first: 0 }, { term: "grades" });
            await db.updateInDatabase("localisation", { capitalise_first: 0 }, { term: "schoolHouse" });

            tester.isGoingTo(
                "navigate to the 9A English class list page and check the terminology terms does not have the first letter capitalised",
            );
            await classList.goto(classId);
            await classList.checkAddPastoralRecordButtonText("Add pastoral Care Test Record");
            await classList.check3DotMenuOptionsText(studentJanaId, {
                "due Work Test": "due Work Test",
                "grades Tests": "grades Tests",
                "pastoral Care Test": "pastoral Care Test",
            });
            await classList.checkColumnHeaders("year Level Test", "house Test");
        } finally {
            tester.isGoingTo("revert the localisation changes in the database");
            await db.updateInDatabase("localisation", { singular_form: "pastoral care" }, { term: "pastoralCare" });
            await db.updateInDatabase("localisation", { singular_form: "year level" }, { term: "yearLevel" });
            await db.updateInDatabase("localisation", { singular_form: "due work" }, { term: "dueWork" });
            await db.updateInDatabase("localisation", { plural_form: "grades" }, { term: "grades" });
            await db.updateInDatabase("localisation", { singular_form: "house" }, { term: "schoolHouse" });
            await db.updateInDatabase("localisation", { capitalise_first: 1 }, { term: "pastoralCare" });
            await db.updateInDatabase("localisation", { capitalise_first: 1 }, { term: "yearLevel" });
            await db.updateInDatabase("localisation", { capitalise_first: 1 }, { term: "dueWork" });
            await db.updateInDatabase("localisation", { capitalise_first: 1 }, { term: "grades" });
            await db.updateInDatabase("localisation", { capitalise_first: 1 }, { term: "schoolHouse" });
        }
    });
});
