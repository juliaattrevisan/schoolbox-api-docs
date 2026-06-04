import { getIconColor } from "../colorUtils";

describe("getIconColor()", () => {
    test("returns the badge colour for badge icons", () => {
        expect(getIconColor("badge")).toBe("var(--sbx-color-icon-badge)");
    });

    test("returns the class colour for teacher icons", () => {
        expect(getIconColor("teacher")).toBe("var(--sbx-color-icon-class)");
    });

    test("returns the course colour for course-builder icons", () => {
        expect(getIconColor("course-builder")).toBe("var(--sbx-color-icon-course)");
    });

    test("returns the course colour for course icons", () => {
        expect(getIconColor("course")).toBe("var(--sbx-color-icon-course)");
    });

    test("returns the folder colour for folder icons", () => {
        expect(getIconColor("folder")).toBe("var(--sbx-color-icon-folder)");
    });

    test("returns the home colour for home icons", () => {
        expect(getIconColor("home")).toBe("var(--sbx-color-icon-home)");
    });

    test("returns the learning activity colour for project icons", () => {
        expect(getIconColor("project")).toBe("var(--sbx-color-icon-learning-activity)");
    });

    test("returns the learning activity colour for dialog-agent icons", () => {
        expect(getIconColor("dialog-agent")).toBe("var(--sbx-color-icon-learning-activity)");
    });

    test("returns the learning activity colour for due-work icons", () => {
        expect(getIconColor("due-work")).toBe("var(--sbx-color-icon-learning-activity)");
    });

    test("returns the learning activity colour for reporting icons", () => {
        expect(getIconColor("reporting")).toBe("var(--sbx-color-icon-learning-activity)");
    });

    test("returns the learning activity colour for task icons", () => {
        expect(getIconColor("task")).toBe("var(--sbx-color-icon-learning-activity)");
    });

    test("returns the learning activity colour for quiz icons", () => {
        expect(getIconColor("quiz")).toBe("var(--sbx-color-icon-learning-activity)");
    });

    test("returns the learning activity colour for lesson icons", () => {
        expect(getIconColor("lesson")).toBe("var(--sbx-color-icon-learning-activity)");
    });

    test("returns the learning activity colour for lti icons", () => {
        expect(getIconColor("lti")).toBe("var(--sbx-color-icon-learning-activity)");
    });

    test("returns the unit colour for unit icons", () => {
        expect(getIconColor("unit")).toBe("var(--sbx-color-icon-unit)");
    });

    test("accepts full icon class names", () => {
        expect(getIconColor("icon-home extra-class")).toBe("var(--sbx-color-icon-home)");
    });

    test("returns currentColor for unknown icons", () => {
        expect(getIconColor("close")).toBe("currentColor");
    });

    test("returns currentColor for empty names", () => {
        expect(getIconColor("")).toBe("currentColor");
    });
});
