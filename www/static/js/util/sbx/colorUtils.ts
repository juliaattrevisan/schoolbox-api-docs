/**
 * Any utility functions related to SBX colours
 */

/**
 * Return the custom property for the icon colour based on the icon name
 * @param name - The icon name
 * @returns The custom property for the icon colour
 */
export function getIconColor(name: string): string {
    if (!name) {
        return "currentColor";
    }
    if (name.includes("course-builder")) {
        return "var(--sbx-color-icon-course)";
    }
    if (name.includes("badge")) {
        return "var(--sbx-color-icon-badge)";
    }
    if (name.includes("teacher")) {
        return "var(--sbx-color-icon-class)";
    }
    if (name.includes("course")) {
        return "var(--sbx-color-icon-course)";
    }
    if (name.includes("folder")) {
        return "var(--sbx-color-icon-folder)";
    }
    if (name.includes("home")) {
        return "var(--sbx-color-icon-home)";
    }
    if (
        name.includes("project") ||
        name.includes("dialog-agent") ||
        name.includes("due-work") ||
        name.includes("reporting") ||
        name.includes("task") ||
        name.includes("quiz") ||
        name.includes("lesson") ||
        name.includes("lti")
    ) {
        return "var(--sbx-color-icon-learning-activity)";
    }
    if (name.includes("unit")) {
        return "var(--sbx-color-icon-unit)";
    }
    return "currentColor";
}
