import type { Course } from "@schoolboxLibrary/types/Course";
import type { Unit } from "@schoolboxLibrary/types/Unit";
import type { Node } from "@schoolboxCurriculum/types/Node";

/**
 * A unit with node usage counts for the different
 * items a curriculum node can be assigned to
 */
export type UnitWithCurriculumNodeUsage = Pick<Unit, "id" | "name"> & {
    usage?: {
        unit?: {
            count: number;
        };
        activity?: {
            count: number;
        };
        lessonPlan?: {
            count: number;
        };
        rubric?: {
            count: number;
        };
        inUseRubric?: {
            count: number;
        };
    };
};

/**
 * A course that includes units with node usage counts
 */
export type CourseWithCurriculumNodeUsage = Pick<Course, "id" | "name"> & {
    units: UnitWithCurriculumNodeUsage[];
    usage?: {
        course?: {
            count: number;
        };
    };
};

/**
 * The different report options (items a curriculum node can be assigned to)
 * available that can be filtered
 */
export enum ReportOptionValues {
    COURSE = "course",
    UNIT = "unit",
    LEARNING_ACTIVITY = "activity",
    LEARNING_ACTIVITY_LESSON_PLAN = "lessonPlan",
    RUBRIC = "rubric",
    IN_USE_RUBRIC = "inUseRubric",
}

/**
 * A report option that can be filtered
 */
export type ReportOption = {
    value: ReportOptionValues;
    label: string;
};

/**
 * A curriculum node specific to curriculum coverage
 */
export type CurriculumCoverageNode = Pick<Node, "id" | "name" | "code"> & {
    children: CurriculumCoverageNode[];
    usage?: {
        courses: CourseWithCurriculumNodeUsage[];
    };
};

/**
 * The expected payload for the curriculum coverage report
 */
export type CurriculumCoveragePayload = {
    data: {
        courses: Course[];
        nodes: CurriculumCoverageNode[];
    };
};
