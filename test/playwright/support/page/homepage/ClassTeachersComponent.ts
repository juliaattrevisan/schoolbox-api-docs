import { expect, type Locator, type Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import Homepage from "@schoolboxTest/playwright/support/page/homepage/Homepage";

export default class ClassTeachersComponent {
    private page: Page;

    private tester: AcceptanceTester;

    private homepage: Homepage;

    /**
     * Template for the data-test attribute used to identify teacher name element in the class teachers component
     */
    static readonly DATA_TEST_TEACHER_NAME = "class-teachers-component-{userId}";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
        this.homepage = new Homepage(tester, page);
    }

    /**
     * Returns the locator for the Class Teachers component on the page
     * @returns - The locator for the [data-component] element with heading "Class Teachers"
     */
    locateComponent(): Locator {
        return this.homepage.locateComponentByName("Class Teachers");
    }

    /**
     * Returns the name paragraph locator for a user in the class teachers component
     * @param userId - The user's id
     * @returns - The locator for the p[data-test] element
     */
    getTeacherName(userId: number): Locator {
        return this.locateComponent().getByTestId(
            ClassTeachersComponent.DATA_TEST_TEACHER_NAME.replace("{userId}", userId.toString()),
        );
    }

    /**
     * Returns the card container locator for a user in the class teachers component
     * @param userId - The user's id
     * @returns - The locator for the div.card element
     */
    getTeacherCard(userId: number): Locator {
        return this.locateComponent().locator(
            `div.card:has([data-test="${ClassTeachersComponent.DATA_TEST_TEACHER_NAME.replace("{userId}", userId.toString())}"])`,
        );
    }

    /**
     * Returns the photo image locator for a user in the class teachers component
     * @param userId - The user's id
     * @returns - The locator for the img element within the teacher card
     */
    getTeacherPhoto(userId: number): Locator {
        return this.getTeacherCard(userId).locator("img");
    }

    /**
     * Returns the meta paragraph locator (group) for a user in the class teachers component
     * @param userId - The user's id
     * @returns - The locator for the p.meta element within the teacher card
     */
    getTeacherMeta(userId: number): Locator {
        return this.getTeacherCard(userId).locator("p.meta");
    }

    /**
     * Checks a teacher is presented in the class teachers component with their name, photo and meta
     * @param userId - The user's id
     * @param expectedName - The expected display name of the teacher
     */
    async checkTeacherPresented(userId: number, expectedName: string): Promise<void> {
        await expect(this.getTeacherName(userId)).toBeVisible();
        await expect(this.getTeacherName(userId)).toContainText(expectedName);
        await expect(this.getTeacherPhoto(userId)).toBeVisible();
        await expect(this.getTeacherMeta(userId)).toBeVisible();
    }
}
