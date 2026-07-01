export type MarkVisibilityOption = {
    value: string;
    label: string;
    readonlyIcon: string;
    disabled?: boolean;
};

const allMarkVisibilityOptions: MarkVisibilityOption[] = [
    // The label text here should match the text in the MARK_VISIBILITY_..._STRING constants in Assessment\IAssessable.
    { value: "staffOnly", label: "&#xe63e; Staff Only", readonlyIcon: "icon-locked" },
    { value: "staffAndStudent", label: "&#xe667; Staff & Student", readonlyIcon: "icon-staff-students" },
    { value: "staffAndParent", label: "&#xe666; Staff & Parent", readonlyIcon: "icon-staff" },
    { value: "staffStudentAndParent", label: "&#xe630; Staff, Student & Parent", readonlyIcon: "icon-group" },
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
