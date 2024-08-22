/**
 * This test creates various groups with different group permissions
 * check whether the group is presented or not for the user that is logged in
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test groupPermission.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import CreateFolder from "@schoolboxTest/playwright/support/page/folder/CreateFolder";
import ManageGroup from "@schoolboxTest/playwright/support/page/group/ManageGroup";
import Resources from "@schoolboxTest/playwright/support/page/resources/Resources";

test.describe("Group Permission Tests", () => {
    test.beforeEach(async ({ tester }) => {
        await tester.loginAsAdmin();
    });

    test("Group Free", async ({ tester, page, db }) => {
        const createFolder = new CreateFolder(page);
        const manageGroup = new ManageGroup(page);
        const folderName = "Group Free";
        const homepageTemplateOption = "Course";
        const studentJunior = "Ms Eden Reed";
        const studentMiddle = "Louisa Smith";
        const studentSenior = "Mr Tim Reed";
        const groupMember = "Ms Eloise White";
        await createFolder.goto();

        tester.isGoingTo("create a group with write permission to teachers");
        await createFolder.typeName(folderName);
        await createFolder.setHomepageTemplate(homepageTemplateOption);
        await createFolder.setAccessPermission(1, "write");
        await createFolder.setAccessPermission(2, "write");
        tester.isGoingTo("set write permission to senior student");
        await createFolder.setAccessPermission(3, "write");
        await createFolder.setAccessPermission(4, "write");
        tester.isGoingTo("set write permission to junior student");
        await createFolder.setAccessPermission(5, "read");
        tester.isGoingTo("set none permission to middle student");
        await createFolder.setAccessPermission(6, "none");
        await createFolder.clickCreateGroupToggle();
        await createFolder.submit();
        const folderId = (await db.grabFromDatabase("folder", "id", {
            deleted_at: null,
            "name LIKE": folderName,
        })) as number;
        expect(folderId).not.toBe(null);
        await manageGroup.setAccessPermission("type-3");
        await manageGroup.clickUpdateButton();
        tester.isGoingTo("add Eloise White into the group");
        await manageGroup.search("elwhite");
        await manageGroup.selectUser(50);
        await manageGroup.clickFromRadioButton("role");
        await manageGroup.clickAddMembers();

        tester.isGoingTo("get logged in as a junior student to check the group is presented to them");
        await tester.impersonate(studentJunior);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).toContainText("Group Free");

        tester.isGoingTo("get logged in as a middle student to check the group is NOT presented to them");
        await tester.impersonateViaModifyUserPage(studentMiddle);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).not.toContainText("Group Free");

        tester.isGoingTo("get logged in as a senior student to check the group is presented to them");
        await tester.impersonateViaModifyUserPage(studentSenior);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).toContainText("Group Free");

        tester.isGoingTo("get logged in as Eloise White and check she is able to leave the group");
        await tester.impersonateViaModifyUserPage(groupMember);
        await page.goto(`https://vagrant.dev.schoolbox.cloud/homepage/${folderId}`);
        await page.click("[data-test=sbx-pop-out-tab-control] [data-test=sbx-pop-out-tab-control-button]");
        await expect(page.locator("#container")).toContainText("Group Free");
        await page.getByText("Leave Group").click();
        await page.click("[data-test=sbx-pop-out-tab-control] [data-test=sbx-pop-out-tab-control-button]");
        await expect(page.locator("#container")).toContainText("Join Group");
    });

    /**
     * create groups with permissions that allow users to join the groups
     * folder: read/group moderated
     * folder: none/group moderated
     * folder: write/group moderated
     * get logged in as that users and check whether that groups are presented to join or not
     * table with scenarios described in 13387
     */
    test("Group Moderated", async ({ tester, page, db }) => {
        const createFolder = new CreateFolder(page);
        const manageGroup = new ManageGroup(page);
        const folderName = "Group Moderated";
        const homepageTemplateOption = "Course";
        const studentJunior = "Ms Eden Reed";
        const studentMiddle = "Louisa Smith";
        const studentSenior = "Mr Tim Reed";
        const groupMember = "Ms Eloise White";
        await createFolder.goto();

        tester.isGoingTo("create a group with write permission to teachers");
        await createFolder.typeName(folderName);
        await createFolder.setHomepageTemplate(homepageTemplateOption);
        await createFolder.setAccessPermission(1, "write");
        await createFolder.setAccessPermission(2, "write");
        tester.isGoingTo("set write permission to senior student");
        await createFolder.setAccessPermission(3, "write");
        await createFolder.setAccessPermission(4, "write");
        tester.isGoingTo("set read permission to junior student");
        await createFolder.setAccessPermission(5, "read");
        tester.isGoingTo("set none permission to middle student");
        await createFolder.setAccessPermission(6, "none");
        await createFolder.clickCreateGroupToggle();
        await createFolder.submit();
        const folderId = (await db.grabFromDatabase("folder", "id", {
            deleted_at: null,
            "name LIKE": folderName,
        })) as number;
        expect(folderId).not.toBe(null);
        await manageGroup.setAccessPermission("type-2");
        await manageGroup.clickUpdateButton();
        tester.isGoingTo("add Jana Reed into the group");
        await manageGroup.search("elwhite");
        await manageGroup.selectUser(50);
        await manageGroup.clickFromRadioButton("role");
        await manageGroup.clickAddMembers();

        tester.isGoingTo("get logged in as a junior student to check the group is presented to them");
        await tester.impersonate(studentJunior);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).toContainText("Group Moderated");

        tester.isGoingTo("get  logged in as a middle student to check the group is NOT presented to them");
        await tester.impersonateViaModifyUserPage(studentMiddle);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).not.toContainText("Group Moderated");

        tester.isGoingTo("get logged in as a senior student to check the group is presented to them");
        await tester.impersonateViaModifyUserPage(studentSenior);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).toContainText("Group Moderated");

        tester.isGoingTo("get logged in as Eloise White and check she is able to leave the group");
        await tester.impersonateViaModifyUserPage(groupMember);
        await page.goto(`https://vagrant.dev.schoolbox.cloud/homepage/${folderId}`);
        await page.click("[data-test=sbx-pop-out-tab-control] [data-test=sbx-pop-out-tab-control-button]");
        await expect(page.locator("#container")).toContainText("Leave Group");
        await page.getByText("Leave Group").click();
        await page.click("[data-test=sbx-pop-out-tab-control] [data-test=sbx-pop-out-tab-control-button]");
        await expect(page.locator("#container")).not.toContainText("Cancel Application");
    });

    /**
     * create groups with permissions that allow users to join the groups
     * folder: read/group moderated
     * folder: none/group moderated
     * folder: write/group moderated
     * get logged in as that users and check whether that groups are presented to join or not
     * table with scenarios described in 13387
     */
    test("Group Restricted", async ({ tester, page, db }) => {
        const createFolder = new CreateFolder(page);
        const manageGroup = new ManageGroup(page);
        const folderName = "Group Restricted";
        const homepageTemplateOption = "Course";
        const studentJunior = "Ms Eden Reed";
        const studentMiddle = "Louisa Smith";
        const studentSenior = "Mr Tim Reed";
        const groupMember = "Ms Eloise White";
        await createFolder.goto();

        tester.isGoingTo("create a group with write permission to teachers");
        await createFolder.typeName(folderName);
        await createFolder.setHomepageTemplate(homepageTemplateOption);
        await createFolder.setAccessPermission(1, "write");
        await createFolder.setAccessPermission(2, "write");
        tester.isGoingTo("set write permission to senior student");
        await createFolder.setAccessPermission(3, "write");
        await createFolder.setAccessPermission(4, "write");
        tester.isGoingTo("set read permission to junior student");
        await createFolder.setAccessPermission(5, "read");
        tester.isGoingTo("set none permission to middle student");
        await createFolder.setAccessPermission(6, "none");
        await createFolder.clickCreateGroupToggle();
        await createFolder.submit();
        const folderId = (await db.grabFromDatabase("folder", "id", {
            deleted_at: null,
            "name LIKE": folderName,
        })) as number;
        expect(folderId).not.toBe(null);

        await manageGroup.setAccessPermission("type-1");
        await manageGroup.clickUpdateButton();
        tester.isGoingTo(`add Jana Reed into the group`);
        await manageGroup.search("jreed");
        await manageGroup.selectUser(25);
        await manageGroup.clickFromRadioButton("role");
        await manageGroup.clickAddMembers();

        tester.isGoingTo("get logged in as a junior student to check the group is NOT presented to them");
        await tester.impersonate(studentJunior);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).not.toContainText("Group Restricted");

        tester.isGoingTo("get  logged in as a middle student to check the group is NOT presented to them");
        await tester.impersonateViaModifyUserPage(studentMiddle);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).not.toContainText("Group Restricted");

        tester.isGoingTo("get logged in as a senior student to check the group is NOT presented to them");
        await tester.impersonateViaModifyUserPage(studentSenior);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).not.toContainText("Group Restricted");

        tester.isGoingTo("get logged in as Jana Reed and check she is able to leave the group");
        await tester.impersonateViaModifyUserPage(groupMember);
        await page.goto(`https://vagrant.dev.schoolbox.cloud/homepage/${folderId}`);
        await page.click("[data-test=sbx-pop-out-tab-control] [data-test=sbx-pop-out-tab-control-button]");
        await expect(page.locator("#container")).not.toContainText("Leave Group");
    });

    /**
     * create a group with Jack Coles as a admin
     * create a sub folder to that group
     * access the sub folder as Jack Coles
     * access the sub folder as a Jane Reed to see access denied
     */
    test("inherit group permission", async ({ tester, page, db }) => {
        const folderName = "Group Root";
        const homepageTemplateOption = "Course";
        const subFolderName = "Group 1";
        const createFolder = new CreateFolder(page);
        const manageGroup = new ManageGroup(page);
        const resources = new Resources(page);
        await createFolder.goto();

        tester.isGoingTo("create a group with write permission to teachers");
        await createFolder.typeName(folderName);
        await createFolder.setHomepageTemplate(homepageTemplateOption);
        await createFolder.setAccessPermission(1, "none");
        await createFolder.setAccessPermission(2, "none");
        tester.isGoingTo("set none permission to senior student");
        await createFolder.setAccessPermission(3, "none");
        await createFolder.setAccessPermission(4, "none");
        tester.isGoingTo("set none permission to junior student");
        await createFolder.setAccessPermission(5, "none");
        tester.isGoingTo("set none permission to middle student");
        await createFolder.setAccessPermission(6, "none");
        await createFolder.clickCreateGroupToggle();
        await createFolder.submit();

        await manageGroup.setAccessPermission("type-1");
        await manageGroup.clickUpdateButton();

        tester.isGoingTo("add Jack Coles into the group");
        await manageGroup.search("jcoles");
        await manageGroup.selectUser(22);
        await manageGroup.clickFromRadioButton("role");
        await manageGroup.clickAddMembers();
        tester.isGoingTo("Making Jack Coles the group admin via database");
        const folderId = (await db.grabFromDatabase("folder", "id", {
            deleted_at: null,
            "name LIKE": folderName,
        })) as number;
        expect(folderId).not.toBe(null);
        await db.updateInDatabase("userAccess", { type: 3 }, { sid: folderId });

        tester.isGoingTo("create a sub folder to the group");
        await createFolder.gotoResources();
        await resources.clickFolderName(folderName);
        await resources.clickMenuItem("folder");
        await createFolder.typeName(subFolderName);
        await createFolder.setHomepageTemplate(homepageTemplateOption);
        await createFolder.submit();

        tester.isGoingTo("Impersonate Jack Coles and access the sub folder");
        await tester.impersonate("Jack Coles");
        await createFolder.gotoResources();
        await resources.clickFolderName(folderName);
        await resources.clickFolderName(subFolderName);
        await resources.clickMenuItem("home");
        await expect(page.locator("#container")).toContainText("Group 1");
        await expect(page.locator("#container")).not.toContainText("You do not have permission to view this page");

        tester.isGoingTo("Impersonate Jane Reed and access the sub folder");
        await tester.impersonate("Jana Reed");
        await page.goto(`https://vagrant.dev.schoolbox.cloud/homepage/${folderId}`);
        await expect(page.locator("#container")).not.toContainText("Group 1");
        await expect(page.locator("#container")).toContainText("You do not have permission to view this page");
    });

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });

    /**
     * Soft-delete data is about to start so it does not get impacted by other tests already executed
     */
    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does impact next suite of tests",
        );
        await db.updateInDatabase("folder", { deleted_at: "2022-01-15 11:22:34" }, { "name LIKE": "Group Free%" });
        await db.updateInDatabase("folder", { deleted_at: "2022-01-15 11:22:34" }, { "name LIKE": "Group Moderated%" });
        await db.updateInDatabase(
            "folder",
            { deleted_at: "2022-01-15 11:22:34" },
            { "name LIKE": "Group Restricted%" },
        );
        await db.updateInDatabase("folder", { deleted_at: "2022-01-15 11:22:34" }, { "name LIKE": "Group Root%" });
        await db.updateInDatabase("folder", { deleted_at: "2022-01-15 11:22:34" }, { "name LIKE": "Group 1" });
    });
});
