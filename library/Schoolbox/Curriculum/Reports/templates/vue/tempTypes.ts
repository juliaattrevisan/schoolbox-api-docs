import type { Node } from "@schoolboxCurriculum/types/Node";

/**
 *
 */
export type Unit = {
    id: number;
    name: string;
};

/**
 *
 */
export type Course = {
    id: number;
    name: string;
    units?: Unit[];
};

/**
 *
 */
export type UnitWithCurriculumNodeUsage = Pick<Unit, "id" | "name"> & {
    _usageCounts: {
        unit: number;
        activity: number;
        lesson: number;
        rubric: number;
    };
};

/**
 *
 */
export type CourseWithCurriculumNodeUsage = Pick<Course, "id" | "name"> & {
    units: UnitWithCurriculumNodeUsage[];
};

/**
 *
 */
export type CurriculumCoverageNode = Pick<Node, "id" | "name" | "code"> & {
    children: CurriculumCoverageNode[];
    usage: {
        courses: CourseWithCurriculumNodeUsage[];
    };
};

/**
 *
 */
export type CurriculumCoveragePayload = {
    data: {
        courses: CourseWithCurriculumNodeUsage[];
        nodes: CurriculumCoverageNode[];
    };
};

/**
 *
 */
export enum ReportOptions {
    UNIT = "unit",
    LEARNING_ACTIVITY = "activity",
    LEARNING_ACTIVITY_LESSON_PLAN = "lessonPlan",
    RUBRIC = "rubric",
}
