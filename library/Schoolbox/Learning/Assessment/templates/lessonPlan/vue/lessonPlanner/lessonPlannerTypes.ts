export type LessonPlanField = {
    id: number | string;
    title: string;
    description: string;
    body: string;
    visibility: "student" | "staff";
    lessonPlanTemplateField?: number; // only exists when modifying an existing lesson plan
};

// The field in the store adds some additional properties to the field to track its state
export interface LessonPlannerStoreField extends LessonPlanField {
    selected: boolean; // Whether the field is selected or not
    editorId: string | null; // The editor ID for the field in the main form, null if not yet created
    suggestedBody?: string; // The suggested body for the field, if it has been generated
    /**
     * Keep track of the initial body of the field, so we can determine whether it has been modified
     * and to show "Generate Lesson Plan" or "Regenerate Lesson Plan". If modifying an existing lesson plan (i.e. field.lessonPlanTemplateField exists),
     * assume that the field has been modified if it has any content in it. Otherwise, the initial body is what was provided in the template.
     */
    initialBody: string;
}

export enum LessonPlannerModalStep {
    GENERATE, // Initial page to generate (or regenerate) the lesson plan for all fields
    GENERATING, // Waiting for the AI to generate the lesson plan
    REVIEW, // Review the generated lesson plan before committing to it
}
