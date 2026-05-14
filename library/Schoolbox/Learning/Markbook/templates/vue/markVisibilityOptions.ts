export type MarkVisibilityOption = {
    value: string;
    label: string;
    readonlyIcon: string;
    disabled?: boolean;
};

const allMarkVisibilityOptions: MarkVisibilityOption[] = [
    { value: "staffOnly", label: "&#xe63e; Staff Only", readonlyIcon: "icon-locked" },
    { value: "staffAndStudent", label: "&#xe667; Staff and Students", readonlyIcon: "icon-staff-students" },
    { value: "staffAndParent", label: "&#xe666; Staff and Parents", readonlyIcon: "icon-staff" },
    { value: "staffStudentAndParent", label: "&#xe666; Staff, Students and their Parents", readonlyIcon: "icon-staff" },
];

/**
 * Build the markbook visibility select options from LMS policy and the current saved value.
 * @param availableVisibilities Visibility values allowed by LMS policy.
 * @param currentVisibility The assessment's current saved visibility.
 * @returns Select options for the markbook visibility row.
 */
export function getMarkVisibilityOptions(
    availableVisibilities: string[],
    currentVisibility: string,
): MarkVisibilityOption[] {
    const available = new Set(availableVisibilities);

    return allMarkVisibilityOptions
        .filter(({ value }) => available.has(value) || value === currentVisibility)
        .map((option) => ({
            ...option,
            disabled: !available.has(option.value),
        }));
}
