/**
 * Any utility functions related to SBX colours
 */

/**
 * Return the CSS colour value for an icon based on the SCSS icon colour rules.
 * @param name - The icon name or class name
 * @returns The matching CSS colour value or currentColor if there is no match
 */
export function getIconColor(name: string): string {
    if (!name) {
        return "currentColor";
    }

    const normalizedName = name.toLowerCase().trim();
    const iconName = normalizedName.includes("") ? normalizedName : `icon-${normalizedName}`;

    if (iconName.includes("course-builder")) {
        return "var(--sbx-color-icon-course)";
    }
    if (iconName.includes("badge")) {
        return "var(--sbx-color-icon-badge)";
    }
    if (iconName.includes("teacher")) {
        return "var(--sbx-color-icon-class)";
    }
    if (iconName.includes("course")) {
        return "var(--sbx-color-icon-course)";
    }
    if (iconName.includes("folder")) {
        return "var(--sbx-color-icon-folder)";
    }
    if (iconName.includes("home")) {
        return "var(--sbx-color-icon-home)";
    }
    if (
        iconName.includes("project") ||
        iconName.includes("dialog-agent") ||
        iconName.includes("due-work") ||
        iconName.includes("reporting") ||
        iconName.includes("task") ||
        iconName.includes("quiz") ||
        iconName.includes("lesson") ||
        iconName.includes("lti")
    ) {
        return "var(--sbx-color-icon-learning-activity)";
    }
    if (iconName.includes("unit")) {
        return "var(--sbx-color-icon-unit)";
    }

    return "currentColor";
}
