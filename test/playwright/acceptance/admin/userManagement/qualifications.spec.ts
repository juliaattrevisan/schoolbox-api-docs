import { test } from "@schoolboxTest/playwright/support/integrations";
import QualificationList from "@schoolboxTest/playwright/support/page/admin/userManagement/QualificationList";

/**
 * check the Qualifications list admin page is accessible at /adminv2/lists/qualification
 * check the columns presented: ID (read-only), Name, System Purpose
 * check the Name field is mandatory – blank Name on Save shows a validation error
 * check the Name field HTML max length attribute is 255
 * check the System Purpose dropdown presents exactly: Not Selected, Diploma Programme Year 2 [DIPLOMA],
 *   Diploma Programme Year 1 [ANTICIPATED], Diploma Course [COURSE]
 * add a new qualification; save and verify it persists after page reload
 * verify the ID cell is read-only after save
 * delete a qualification; verify it is removed after page reload
 * verify row grab handles are visible; reordering rows persists after save and page reload
 */
test.describe.serial("Admin User Management - Lists: Qualifications", () => {
    let qualificationList: QualificationList;

    const qualificationName = "Test Qualification PLW";
    const qualificationNameSecond = "Test Qualification PLW 2";
    const qualificationNameThird = "Test Qualification PLW 3";

    test.beforeEach(async ({ tester, page }) => {
        qualificationList = new QualificationList(tester, page);
        await tester.loginAsAdmin();
    });

    test("the Qualifications list admin page is accessible and presents the correct columns", async ({ tester }) => {
        tester.isGoingTo("navigate to the Qualifications list admin page");
        await qualificationList.goto();
        tester.isGoingTo("check the column headers ID, Name and System Purpose are presented");
        await qualificationList.checkColumnsArePresented();
    });

    test(
        "the Name field is mandatory with a max length of 255 and the System Purpose dropdown presents all expected options",
        async ({ tester, page }) => {
            tester.isGoingTo("navigate to the Qualifications list page and add a new blank row");
            await qualificationList.goto();
            await qualificationList.clickAddNewRowButton();
            const newRowIndex = (await qualificationList.getRowCount()) - 1;

            tester.isGoingTo("check the System Purpose dropdown contains all expected options");
            await qualificationList.checkSystemPurposeOptionsArePresented(newRowIndex);

            tester.isGoingTo("check the Name input has the correct HTML maxlength attribute (255)");
            await qualificationList.checkNameMaxLengthAttribute(newRowIndex);

            tester.isGoingTo("click Save without filling in the Name field and check a validation error is shown");
            await qualificationList.clickSaveButton();
            await qualificationList.checkValidationErrorIsShownForRow(newRowIndex);

            tester.isGoingTo("reload the page to discard the unsaved row");
            await page.reload();
            await qualificationList.waitForListLoaded();
        },
    );

    test("add a new qualification, save and verify it persists after page reload", async ({ tester, page }) => {
        tester.isGoingTo("navigate to the Qualifications list page and add a new row");
        await qualificationList.goto();
        await qualificationList.clickAddNewRowButton();
        const newRowIndex = (await qualificationList.getRowCount()) - 1;

        tester.isGoingTo(
            "fill in the Name field and select Diploma Programme Year 2 [DIPLOMA] as the System Purpose",
        );
        await qualificationList.fillNameInRow(newRowIndex, qualificationName);
        await qualificationList.selectSystemPurposeInRow(newRowIndex, QualificationList.SYSTEM_PURPOSE_DIPLOMA);

        tester.isGoingTo("click Save and confirm the save-successful toast is shown");
        await qualificationList.clickSaveButton();
        await qualificationList.checkSaveSuccessToast();

        tester.isGoingTo("reload the page and verify the new qualification is still in the list");
        await page.reload();
        await qualificationList.waitForListLoaded();
        await qualificationList.checkRowWithNameIsPresented(qualificationName);

        tester.isGoingTo("check the ID cell for the persisted row is read-only");
        const savedRowIndex = await qualificationList.getRowIndexByName(qualificationName);
        await qualificationList.checkIdIsReadOnly(savedRowIndex);
    });

    test("delete a qualification and verify it is removed after page reload", async ({ tester, page }) => {
        tester.isGoingTo(
            "navigate to the Qualifications list page and confirm the qualification from the previous test is present",
        );
        await qualificationList.goto();
        await qualificationList.checkRowWithNameIsPresented(qualificationName);

        tester.isGoingTo("click the delete button for the qualification and confirm deletion in the modal");
        const rowIndex = await qualificationList.getRowIndexByName(qualificationName);
        await qualificationList.clickDeleteRowButton(rowIndex);
        await qualificationList.confirmDeleteRow();

        tester.isGoingTo("reload the page and verify the deleted qualification is no longer present");
        await page.reload();
        await qualificationList.waitForListLoaded();
        await qualificationList.checkRowWithNameIsNotPresented(qualificationName);
    });

    test(
        "rows present drag handles and reordering persists after save and page reload",
        async ({ tester, page }) => {
            tester.isGoingTo("navigate to the Qualifications list page and add two new qualification rows");
            await qualificationList.goto();

            await qualificationList.clickAddNewRowButton();
            const firstNewRowIndex = (await qualificationList.getRowCount()) - 1;
            await qualificationList.fillNameInRow(firstNewRowIndex, qualificationNameSecond);

            await qualificationList.clickAddNewRowButton();
            const secondNewRowIndex = (await qualificationList.getRowCount()) - 1;
            await qualificationList.fillNameInRow(secondNewRowIndex, qualificationNameThird);

            tester.isGoingTo("save both new qualifications and confirm the save-successful toast");
            await qualificationList.clickSaveButton();
            await qualificationList.checkSaveSuccessToast();

            tester.isGoingTo("check that a drag-and-drop grab handle is visible on every row");
            await qualificationList.checkGrabHandlesArePresented();

            tester.isGoingTo("reload the page to get the stable persisted row order");
            await page.reload();
            await qualificationList.waitForListLoaded();

            tester.isGoingTo(
                "verify the initial order (PLW 2 before PLW 3) and then drag PLW 2 to after PLW 3",
            );
            await qualificationList.checkRowAIsBeforeRowB(qualificationNameSecond, qualificationNameThird);
            const indexSecond = await qualificationList.getRowIndexByName(qualificationNameSecond);
            const indexThird = await qualificationList.getRowIndexByName(qualificationNameThird);
            await qualificationList.dragRowToPosition(indexSecond, indexThird);

            tester.isGoingTo("save the reordered list and verify the new order persists after page reload");
            await qualificationList.clickSaveButton();
            await qualificationList.checkSaveSuccessToast();
            await page.reload();
            await qualificationList.waitForListLoaded();
            await qualificationList.checkRowAIsBeforeRowB(qualificationNameThird, qualificationNameSecond);
        },
    );

    /**
     * Soft-deleting test data so it does not impact other test suites.
     */
    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update deleted_at for qualifications created in this test so they do not impact the next suite of tests",
        );
        await db.updateInDatabase(
            "lu_qualification",
            { deleted_at: "2022-01-15 11:22:34" },
            { "name like": "Test Qualification PLW%", deleted_at: null },
        );
    });
});
