import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";

export type Unit = {
    id: number;
    title: string;
    openDate: string;
    closeDate: string;
    relativeOpenDateFormatted: string;
    relativeCloseDateFormatted: string;
    assessments: Activity[];
};

export type CourseAssessments = {
    units: Unit[];
};
