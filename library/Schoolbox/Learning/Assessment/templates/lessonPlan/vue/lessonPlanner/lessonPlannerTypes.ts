export type LessonPlanField = {
    id: number;
    title: string;
    body: string;
    meta: string;
    visibility: "student" | "staff";
    lessonPlanTemplateField?: number; // only exists when modifying an existing lesson plan
};

export enum LessonPlannerModalStep {
    GENERATE, // Initial page to generate (or regenerate) the lesson plan for all fields
    GENERATING, // Waiting for the AI to generate the lesson plan
    REVIEW, // Review the generated lesson plan before committing to it
}
