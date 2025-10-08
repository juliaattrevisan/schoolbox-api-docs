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
        const createFolder = new CreateFolder(tester, page);
        const manageGroup = new ManageGroup(page);
        const folderName = "Group Free";
        const homepageTemplateOption = "Course";
        const studentJunior = "Ms Eden Reed";
        const studentMiddle = "Louisa Smith";
        const studentSenior = "Mr Tim Reed";
        const groupMember = "Ms Eloise White";
        const schoolCode = "";

        // sets write access permission for Administration, Teacher, senior student  and parent, read access to junior student and none access to middle student
        await createFolder.createFolder(
            folderName,
            homepageTemplateOption,
            db,
            [
                [1, "write"],
                [2, "write"],
                [3, "write"],
                [4, "write"],
                [5, "read"],
                [6, "none"],
            ],
            schoolCode,
            true,
        );

        const folderId = (await db.grabFromDatabase("folder", "id", {
            deleted_at: null,
            "name LIKE": folderName,
        })) as number;
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
        await tester.impersonateViaUserProfilePage(studentMiddle);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).not.toContainText("Group Free");

        tester.isGoingTo("get logged in as a senior student to check the group is presented to them");
        await tester.impersonateViaUserProfilePage(studentSenior);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).toContainText("Group Free");

        tester.isGoingTo("get logged in as Eloise White and check she is able to leave the group");
        await tester.impersonateViaUserProfilePage(groupMember);
        await page.goto(`https://vagrant.dev.schoolbox.cloud/homepage/${folderId}`);
        await page.click("[data-test=sbx-pop-out-tab-control-button]");
        await expect(page.locator("#container")).toContainText("Group Free");
        await page.getByText("Leave Group").click();
        await page.click("[data-test=sbx-pop-out-tab-control-button]");
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
        const createFolder = new CreateFolder(tester, page);
        const manageGroup = new ManageGroup(page);
        const folderName = "Group Moderated";
        const homepageTemplateOption = "Course";
        const studentJunior = "Ms Eden Reed";
        const studentMiddle = "Louisa Smith";
        const studentSenior = "Mr Tim Reed";
        const groupMember = "Ms Eloise White";
        const schoolCode = "";

        // sets write access permission for Administration, Teacher, senior student  and parent, read access to junior student and none access to middle student
        await createFolder.createFolder(
            folderName,
            homepageTemplateOption,
            db,
            [
                [1, "write"],
                [2, "write"],
                [3, "write"],
                [4, "write"],
                [5, "read"],
                [6, "none"],
            ],
            schoolCode,
            true,
        );

        const folderId = (await db.grabFromDatabase("folder", "id", {
            deleted_at: null,
            "name LIKE": folderName,
        })) as number;

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
        await tester.impersonateViaUserProfilePage(studentMiddle);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).not.toContainText("Group Moderated");

        tester.isGoingTo("get logged in as a senior student to check the group is presented to them");
        await tester.impersonateViaUserProfilePage(studentSenior);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).toContainText("Group Moderated");

        tester.isGoingTo("get logged in as Eloise White and check she is able to leave the group");
        await tester.impersonateViaUserProfilePage(groupMember);
        await page.goto(`https://vagrant.dev.schoolbox.cloud/homepage/${folderId}`);
        await page.click("[data-test=sbx-pop-out-tab-control-button]");
        await expect(page.locator("#container")).toContainText("Leave Group");
        await page.getByText("Leave Group").click();
        await page.click("[data-test=sbx-pop-out-tab-control-button]");
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
        const createFolder = new CreateFolder(tester, page);
        const manageGroup = new ManageGroup(page);
        const folderName = "Group Restricted";
        const homepageTemplateOption = "Course";
        const studentJunior = "Ms Eden Reed";
        const studentMiddle = "Louisa Smith";
        const studentSenior = "Mr Tim Reed";
        const groupMember = "Ms Eloise White";
        const schoolCode = "";
        await createFolder.goto();

        // sets write access permission for Administration, Teacher, senior student  and parent, read access to junior student and none access to middle student
        await createFolder.createFolder(
            folderName,
            homepageTemplateOption,
            db,
            [
                [1, "write"],
                [2, "write"],
                [3, "write"],
                [4, "write"],
                [5, "read"],
                [6, "none"],
            ],
            schoolCode,
            true,
        );

        const folderId = (await db.grabFromDatabase("folder", "id", {
            deleted_at: null,
            "name LIKE": folderName,
        })) as number;

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
        await tester.impersonateViaUserProfilePage(studentMiddle);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).not.toContainText("Group Restricted");

        tester.isGoingTo("get logged in as a senior student to check the group is NOT presented to them");
        await tester.impersonateViaUserProfilePage(studentSenior);
        await page.goto("https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join");
        await expect(page.locator("#container")).not.toContainText("Group Restricted");

        tester.isGoingTo("get logged in as Jana Reed and check she is able to leave the group");
        await tester.impersonateViaUserProfilePage(groupMember);
        await page.goto(`https://vagrant.dev.schoolbox.cloud/homepage/${folderId}`);
        await page.click("[data-test=sbx-pop-out-tab-control-button]");
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
        const schoolCode = "";
        const createFolder = new CreateFolder(tester, page);
        const manageGroup = new ManageGroup(page);
        const resources = new Resources(page);

        // sets none access permission for Administration, Teacher, senior student, parent, junior student and middle student
        await createFolder.createFolder(
            folderName,
            homepageTemplateOption,
            db,
            [
                [1, "none"],
                [2, "none"],
                [3, "none"],
                [4, "none"],
                [5, "none"],
                [6, "none"],
            ],
            schoolCode,
            true,
        );

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
        await page.waitForTimeout(3000);
        await db.updateInDatabase("userAccess", { type: 3 }, { sid: folderId, uid: "22" });

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

    test.afterAll(async ({ db, api }) => {
        // Soft-delete data to not interfere with other tests
        await api.deleteFoldersByName(db, [
            "Group Free%",
            "Group Moderated%",
            "Group Restricted%",
            "Group Root%",
            "Group 1",
        ]);
    });
});
