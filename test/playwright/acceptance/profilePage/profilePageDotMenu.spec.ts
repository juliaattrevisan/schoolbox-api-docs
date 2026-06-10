import { test } from "@schoolboxTest/playwright/support/integrations";
import Common from "@schoolboxTest/playwright/support/page/Common";
import ProfilePage from "@schoolboxTest/playwright/support/page/profilePage/ProfilePage";

/**
 * Tests profile page header action visibility and URLs for superuser, staff,
 * impersonator, student and parent users.
 *
 * 1. Provision sales.tar.gz data.
 * 2. Execute DOCKER=1 npx playwright test test/playwright/acceptance/profilePage/profilePageDotMenu.spec.ts.
 */

test.describe("Profile Page 3-dot Menu", () => {
    test.describe.configure({ mode: "serial" });

    const edenReedId = Common.USER_ID_EDENREED;
    const jackColesId = Common.USER_ID_JACKCOLES;
    const janaReedId = Common.USER_ID_JANAREED;
    const jenniReedUserName = Common.USER_JENNIREED;

    test.beforeAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("disable Eden Reed for profile page 3-dot menu tests");
        await db.updateInDatabase("user", { enabled: 0 }, { id: edenReedId });
    });

    test("Super users can view the buttons modify, impersonate and create pastoral record", async ({
        tester,
        page,
    }) => {
        const profilePage = new ProfilePage(tester, page);

        tester.isGoingTo("navigate to the Jana Reed profile page as a superuser");
        await tester.loginAsAdmin();
        await profilePage.goto(janaReedId);

        tester.isGoingTo("check the profile page 3-dot menu options and urls for Jana Reed");
        await profilePage.openDotMenu();
        await profilePage.checkDotMenuOptionsVisibility([
            { option: "modify", isVisible: true },
            { option: "impersonate", isVisible: true },
            { option: "create-pastoral-record", isVisible: true },
        ]);
        await profilePage.checkDotMenuActionUrl("modify", new RegExp(`/adminv2/user/${janaReedId}/modify`));
        await profilePage.checkDotMenuActionUrl("impersonate", new RegExp(`/user/switch/${janaReedId}`));
        await profilePage.checkDotMenuActionUrl(
            "create-pastoral-record",
            new RegExp(`/pastoral/student/${janaReedId}/record/insert`),
        );

        tester.isGoingTo("navigate to the disabled Eden Reed profile page and check impersonate is disabled");
        await profilePage.goto(edenReedId);
        await profilePage.checkImpersonateIsDisabled();
    });

    test("Staff users can see the create pastoral record button but cannot see the the impersonate and modify buttons", async ({
        tester,
        page,
    }) => {
        const profilePage = new ProfilePage(tester, page);

        tester.isGoingTo("impersonate Jack Coles and navigate to the Jana Reed profile page");
        await tester.impersonateViaUserProfilePage(Common.USER_JACKCOLES);
        await profilePage.goto(janaReedId);

        tester.isGoingTo("check Jack Coles can see the create pastoral record button without the 3-dot menu");
        await profilePage.checkSingleHeaderActionButton(
            "Create Pastoral Record",
            new RegExp(`/pastoral/student/${janaReedId}/record/insert`),
        );
        await profilePage.checkDotMenuIsNotPresented();
    });

    test("Impersonator users can see the impersonate and create pastoral record buttons but cannot see the the modify button", async ({
        db,
        tester,
        page,
    }) => {
        const profilePage = new ProfilePage(tester, page);
        const impersonatorPermissions = [
            { uid: jackColesId, service: 15, sid: 5, type: 3 },
            { uid: jackColesId, service: 15, sid: 6, type: 3 },
        ];

        try {
            tester.isGoingTo("give Jack Coles impersonator permission for middle and senior students");
            for (const impersonatorPermission of impersonatorPermissions) {
                await db.haveInDatabase("userAccess", impersonatorPermission);
            }

            tester.isGoingTo("impersonate Jack Coles and navigate to the Jana Reed profile page");
            await tester.impersonateViaUserProfilePage(Common.USER_JACKCOLES);
            await profilePage.goto(janaReedId);

            tester.isGoingTo("check Jack Coles can impersonate Jana Reed and create a pastoral record");
            await profilePage.openDotMenu();
            await profilePage.checkDotMenuOptionsVisibility([
                { option: "modify", isVisible: false },
                { option: "impersonate", isVisible: true },
                { option: "create-pastoral-record", isVisible: true },
            ]);

            tester.isGoingTo("navigate to the disabled Eden Reed profile page and check impersonate is disabled");
            await profilePage.goto(edenReedId);
            await profilePage.checkImpersonateIsDisabled();
        } finally {
            tester.isGoingTo("remove Jack Coles impersonator permissions for middle and senior students");
            for (const impersonatorPermission of impersonatorPermissions) {
                await db.dontHaveInDatabase("userAccess", impersonatorPermission);
            }
        }
    });

    test("Students and parents cannot see the profile page 3-dot button", async ({ tester, page }) => {
        const profilePage = new ProfilePage(tester, page);

        tester.isGoingTo("impersonate Jana Reed and navigate to the Eden Reed profile page");
        await tester.impersonateViaUserProfilePage(Common.USER_JANAREED);
        await profilePage.goto(edenReedId);
        await profilePage.checkDotMenuIsNotPresented();

        tester.isGoingTo("impersonate Jenni Reed and navigate to the Jana Reed profile page");
        await tester.impersonateViaUserProfilePage(jenniReedUserName);
        await profilePage.goto(janaReedId);
        await profilePage.checkDotMenuIsNotPresented();
    });

    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo("enable Eden Reed after profile page 3-dot menu tests");
        await db.updateInDatabase("user", { enabled: 1 }, { id: edenReedId });
    });
});
