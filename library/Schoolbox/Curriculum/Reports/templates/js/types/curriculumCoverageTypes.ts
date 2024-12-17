import type { Course } from "@schoolboxLibrary/types/Course";
import type { Unit } from "@schoolboxLibrary/types/Unit";
import type { Node } from "@schoolboxCurriculum/types/Node";

/**
 * The expected payload for the curriculum coverage report
 */
export type CurriculumCoveragePayload = {
    data: {
        courses: Course[];
        nodes: CurriculumCoverageNode[];
    };
    metadata: {
        count: number;
        cursor: {
            current: string | null;
            next: string | null;
        };
    };
};

/**
 * A unit with node usage counts for the different
 * items a curriculum node can be assigned to
 */
export type UnitWithCurriculumNodeUsage = Pick<Unit, "id" | "name"> & {
    usage: {
        unit: {
            count: number;
        };
        activity: {
            count: number;
        };
        lessonPlan: {
            count: number;
        };
        rubric: {
            count: number;
        };
        inUseRubric: {
            count: number;
        };
    };
    hasNoUsages: boolean; // to track if the unit has no usages (including on children nodes)
};

/**
 * A course that includes units with node usage counts
 */
export type CourseWithCurriculumNodeUsage = Pick<Course, "id" | "name"> & {
    units: UnitWithCurriculumNodeUsage[];
    usage: {
        course: {
            count: number;
        };
    };
    hasNoCourseUsages: boolean; // to track if the course has no course column usages (including on children nodes)
    hasNoTotalUsages: boolean; // to track if the course has no total usages (including on children nodes)
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
    children: CurriculumCoverageNode[] | null;
    usage: {
        courses: CourseWithCurriculumNodeUsage[];
    };
    hasDefaultsSet?: boolean; // to track if the node has defaults set
    isVisible: boolean; // to track if the node is visible in the report
    previousIsVisible: boolean; // to track if the node was visible previously (used when moving between the only show gaps toggle)
    isExpanded: boolean; // to track if the node is expanded in the report
    hasNoUsages: boolean; // to track if the node has no usages (including on children nodes)
    storedUsages: {
        // to store saved usages for the node
        [key: string]: number;
    };
};

/**
 * Used in activity and lesson plan
 */
export type Item = {
    name: string;
    icon?: string; // only for activity
    _links: {
        view: string;
    };
};

/**
 * A rubric capability indicator
 */
type RubricCapabilityIndicator = {
    name: string;
};

/**
 * A rubric capability
 */
type RubricCapability = {
    name: string;
    indicators: RubricCapabilityIndicator[];
};

/**
 * The expected payload for a rubric
 */
export type Rubric = {
    capabilities: RubricCapability[];
    _links: {
        view: string;
    };
};

/**
 * The expected payload for in-use rubric
 */
type InUseRubric = Item & {
    rubric: Rubric;
};

/**
 * The details of the individual usage of a curriculum node
 */
export type IndividualUsage = {
    onCourse?: boolean; // if the node is assigned to the course (this is handled internally, so doesn't require a fetch)
    onUnit: boolean; // if the node is assigned to the unit
    activity?: Item[]; // the activities the node is assigned to
    lessonPlan?: Item[]; // the lesson plans the node is assigned to
    rubric?: {
        course?: Rubric;
        unit?: Rubric;
    };
    inUseRubric?: InUseRubric[];
};
