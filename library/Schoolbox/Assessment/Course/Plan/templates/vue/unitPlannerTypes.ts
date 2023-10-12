import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import type { Node } from "@schoolboxCurriculum/types/Node";

export type Assessment = {
    id: number;
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
    curriculumCodes: Node[];
    rubricCapabilities: RubricCapability[];
    textboxes: Record<string, TextboxContent | null>;
    extraTextboxes: number;
};
