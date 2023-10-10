import { Activity } from "@schoolboxAssessment/Types/assessmentTypes";

export type Assessment = {
    id: number;
};

export type CurriculumCode = {
    breadcrumb: string;
    code: string;
    description: string;
    id: number;
    name: string;
    children: CurriculumCode[];
    reference: null; // currently available but unused
    uuid: null; // currently available but unused
};

export type RubricCapability = {
    id: number;
    name: string;
    indicators: {
        id: number;
        name: string;
    }[];
};

export type TextboxContent = {
    id: number;
    body: string;
};

export type Unit = {
    id: number;
    title: string;
    homepage: string;
    activities: Activity[];
    curriculumCodes: CurriculumCode[];
    rubricCapabilities: RubricCapability[];
    textboxes: Record<string, TextboxContent | null>;
    extraTextboxes: number;
};
