/**
 * The different events that can be dispatched by a lesson plan page
 */
export enum LessonPlanEvent {
    TO_STUDENT_VIEW = "LESSON-PLAN__TO_STUDENT_VIEW", // switched to student view, which can alter the visibility of various elements on the page
    TO_STAFF_VIEW = "LESSON-PLAN__TO_STAFF_VIEW", // switched to staff view, which can alter the visibility of various elements on the page
}
