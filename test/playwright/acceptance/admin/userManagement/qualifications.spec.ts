import { expect, test } from "@schoolboxTest/playwright/support/integrations";
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

    test("the Name field is mandatory with a max length of 255 and the System Purpose dropdown presents all expected options", async ({
        tester,
    }) => {
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
    });

    test("add a new qualification, save and verify it persists after page reload", async ({ tester, page }) => {
        tester.isGoingTo("navigate to the Qualifications list page and add a new row");
        await qualificationList.goto();
        await qualificationList.clickAddNewRowButton();
        const newRowIndex = (await qualificationList.getRowCount()) - 1;

        tester.isGoingTo("fill in the Name field and select Diploma Programme Year 2 [DIPLOMA] as the System Purpose");
        await qualificationList.fillNameInRow(newRowIndex, qualificationName);
        await qualificationList.selectSystemPurposeInRow(newRowIndex, QualificationList.SYSTEM_PURPOSE_DIPLOMA);

        tester.isGoingTo("click Save and confirm the save-successful toast is shown");
        await qualificationList.clickSaveButton();
        await qualificationList.checkSaveSuccessToast();

        tester.isGoingTo("reload the page and verify the new qualification is still in the list");
        await page.reload();
        await qualificationList.checkRowWithNameIsPresented(qualificationName);

        tester.isGoingTo("check the ID cell for the persisted row is read-only");
        const savedRowIndex = await qualificationList.getRowIndexByName(qualificationName);
        await qualificationList.checkIdIsReadOnly(savedRowIndex);
    });

    test("delete a qualification and verify it is removed after page reload", async ({ tester, page, db }) => {
        tester.isGoingTo(
            "navigate to the Qualifications list page and confirm the qualification from the previous test is present",
        );
        await qualificationList.goto();
        await qualificationList.checkRowWithNameIsPresented(qualificationName);

        tester.isGoingTo("click the delete button for the qualification and confirm deletion in the modal");

        const rowId = (await db.grabFromDatabase("lu_qualification", "id", {
            deleted_at: null,
            "name like": qualificationName,
        })) as string;
        await qualificationList.clickDeleteRowButton(rowId);
        await qualificationList.confirmDeleteRow();

        tester.isGoingTo("reload the page and verify the deleted qualification is no longer present");
        await page.reload();
        await qualificationList.checkRowWithNameIsNotPresented(qualificationName);
    });

    test("rows present drag handles and reordering persists after save and page reload", async ({
        tester,
        page,
        db,
    }) => {
        try {
            tester.isGoingTo(
                "navigate to the Qualifications list, resort the items and verify the order is persisted after save and page reload",
            );
            const item1 = 1;
            const item2 = 3;

            await qualificationList.goto();

            const initialItemsOrder = await qualificationList.getItemsOrder();

            tester.isGoingTo("Verify Pre-unit Quiz to be above Post to Forum: Prologue in the initial order");
            expect(initialItemsOrder.indexOf(item1.toString())).toBeLessThan(
                initialItemsOrder.indexOf(item2.toString()),
            );

            tester.isGoingTo("drag the the item VCE above DP Year 1");
            await qualificationList.dragAndDropItem(item2, item1);
            const newItemOrder = await qualificationList.getItemsOrder();

            tester.isGoingTo("verify that the items have been correctly reordered");
            expect(newItemOrder.indexOf(item2.toString())).toBeLessThan(newItemOrder.indexOf(item1.toString()));

            await page.reload();

            const finalItemOrder = await qualificationList.getItemsOrder();
            expect(finalItemOrder).toEqual(newItemOrder);
        } finally {
            tester.isGoingTo("reorder the items back to the initial order to not impact other tests");
            await db.updateInDatabase("lu_qualification", { sequence: 1 }, { "name like": "DP Year 1" });
            await db.updateInDatabase("lu_qualification", { sequence: 3 }, { "name like": "VCE" });
        }
    });

    test("checking the empty state is presented when there are no qualifications", async ({ tester, db }) => {
        try {
            tester.isGoingTo("soft delete all qualifications in the database to trigger the empty state");
            await db.updateInDatabase("lu_qualification", { deleted_at: "2022-01-15 11:22:34" }, { deleted_at: null });

            tester.isGoingTo("navigate to the Qualifications list admin page and check the empty state is presented");
            await qualificationList.goto();
            await qualificationList.checkEmptyStateIsPresented();
        } finally {
            tester.isGoingTo("revert the soft delete of qualifications to not impact other tests");
            await db.updateInDatabase("lu_qualification", { deleted_at: null }, { deleted_at: "2022-01-15 11:22:34" });
        }
    });

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
