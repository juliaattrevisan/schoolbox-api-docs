export type LessonPlanField = {
    id: number;
    title: string;
    body: string;
    meta: string | null;
    visibility: "student" | "staff";
    lessonPlanTemplateField?: number; // only exists when modifying an existing lesson plan
};
