import type { Locator, Page } from "@playwright/test";
import type AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";

export default class ClassList {
    private page: Page;

    private tester: AcceptanceTester;

    static readonly URL = "/learning/class/{classId}";

    static readonly DATA_TEST_STUDENT_PROFILE_FLAGS = "class-list-pastoral-flags-{studentId}";

    static readonly DATA_TEST_PROFILE_FLAG = "pastoral-flags-{typeId}-{subtypeId}";

    constructor(page: Page, tester: AcceptanceTester) {
        this.page = page;
        this.tester = tester;
    }

    /**
     * Navigates to the class list page
     * @param classId - The class id
     */
    async goto(classId: number): Promise<void> {
        await this.page.goto(ClassList.URL.replace("{classId}", classId.toString()));
    }

    /**
     * Returns a flag label locator
     * @param studentId - The student id
     * @param typeId - The pastoral type id
     * @param subtypeId - The pastoral subtype id
     * @returns - The locator for the flag label
     */
    getFlagLabel(studentId: number, typeId: number, subtypeId: number): Locator {
        const student = this.page.getByTestId(
            ClassList.DATA_TEST_STUDENT_PROFILE_FLAGS.replace("{studentId}", studentId.toString()),
        );
        return student.getByTestId(
            ClassList.DATA_TEST_PROFILE_FLAG.replace("{typeId}", typeId.toString()).replace(
                "{subtypeId}",
                subtypeId.toString(),
            ),
        );
    }
}
