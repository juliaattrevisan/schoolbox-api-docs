import type { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";

// TODO: refine these along with builderTypes.ts, the assessment API, etc

/**
 * @see /library/Schoolbox/Assessment/Rubric/Grid/Translator/ModelToArray/Base.php
 */
export type RubricGrid = {
    id: number; // currently the assessment ID
    title: string;
    weight: number | null;
    levels: RubricGridLevel[];
    capabilities: RubricGridCapability[];
    capabilityMarkType: MarkTypeSlug | null;
};

export type RubricGridLevel = {
    id: number;
    name: string;
};

export type RubricGridCapability = {
    id: number;
    name: string;
    indicators: RubricGridIndicator[];
};

export type RubricGridIndicator = {
    id: number;
    name: string;
    descriptors: RubricGridDescriptor[];
};

export type RubricGridDescriptor = {
    id: number;
    description: string | null;
    value:
        | { left: number | null; right: number | null } // if using mark range
        | { max: number | null } // no mark range, just show max value
        | null; // if no value, or if no access to see the value
};

/**
 * @see /library/Schoolbox/Assessment/Rubric/MarkedGrid/Translator/ModelToArray.php
 */
export type RubricMarkedGrid = {
    gridId: RubricGrid["id"];
    /**
     * the overall human-readable mark given
     * null if it shouldn't be displayed / doesn't exist (e.g. for self assessment feedback, if feedback.isSelfGrading is false)
     */
    humanReadableMark: string | null; // TODO: markable grid uses instructor mark type instead for self assessment feedback
    capabilities: RubricMarkedGridCapability[];
};

export type RubricMarkedGridCapability = {
    id: RubricGridCapability["id"];
    humanReadableMark: string | null;
    indicators: RubricMarkedGridIndicator[];
};

export type RubricMarkedGridIndicator = {
    id: RubricGridIndicator["id"];
    humanReadableMark: string | null;
    /** The selected descriptor ID, if any */
    descriptorId: RubricGridDescriptor["id"] | null;
};
