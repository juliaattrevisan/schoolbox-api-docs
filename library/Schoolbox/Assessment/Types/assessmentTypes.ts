import type { ProgressTickClass } from "./progressTick";

// All assessment types, as per library/Schoolbox/Learning/Assessment/AssessmentType.php
export const ACTIVITY_TYPE_PROJECT = "project";
export const ACTIVITY_TYPE_DIALOG_AGENT = "dialogAgent";
export const ACTIVITY_TYPE_DUE_WORK = "dueWork";
export const ACTIVITY_TYPE_TASK = "task";
export const ACTIVITY_TYPE_QUIZ = "quiz";
export const ACTIVITY_TYPE_LTI_ASSESSMENT = "lti";
export const ACTIVITY_TYPE_LESSON_PLAN = "lessonPlan";
export const ACTIVITY_TYPE_REPORTING = "reporting";
export type ActivityType =
    | typeof ACTIVITY_TYPE_PROJECT
    | typeof ACTIVITY_TYPE_DIALOG_AGENT
    | typeof ACTIVITY_TYPE_DUE_WORK
    | typeof ACTIVITY_TYPE_TASK
    | typeof ACTIVITY_TYPE_QUIZ
    | typeof ACTIVITY_TYPE_LTI_ASSESSMENT
    | typeof ACTIVITY_TYPE_LESSON_PLAN
    | typeof ACTIVITY_TYPE_REPORTING;

export type Activity = {
    id: number;
    title: string;
    unit: string;
    projectName: string;
    assessmentType: ActivityType;
    assessments: Activity[] | null;
    dueAt: {
        atom: string;
        long: string;
        relative: string;
    } | null;
    isSelfAssessableTask: boolean;
    // _links is itself null if the user does not have access to any actions
    // Individual links are null if the user does not have access to the action, or if the action is not available
    _links: {
        status: string | null;
        summary: string | null;
        modify: string | null;
        modifyContent: string | null; // learnosity modify contents
        configureAgent: string | null; // dialog agent configure agent
        delete: string | null;
        export: string | null; // Spec assessments can be exported
        studentParticipation: string | null;
    } | null;
    completionCount: number | null;
    studentCount: number | null;
    progressTick: {
        postRouteUrl: string | null;
        progressClass: ProgressTickClass | null;
    } | null;
};

export type ActivitiesListRoutes = {
    create: string | null;
    reorder: string | null;
};
