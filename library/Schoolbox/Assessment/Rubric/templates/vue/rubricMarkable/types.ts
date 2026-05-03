import type { MarkScheme } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";

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
    capabilityMarkScheme: MarkScheme;
};

export type RubricGridLevel = {
    id: number;
    name: string;
};

export type RubricGridCapability = {
    id: number;
    name: string;
    maxValue: number | null;
    /**
     * Should capability marks show for the given capability usage?
     *
     * Marks will only show if:
     * - this grid is on an activity which supports mark-per-capability
     * - the max value for this grid's usage of `$capability` is > 0
     *
     * TODO: how required is this, especially with grid.capabilityMarkType being checkable
     */
    showCapabilityMark: boolean;
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
    value: string | null; // Form value (capabilityMark.retrieveHumanReadableValueAsFormInput)
    indicators: RubricMarkedGridIndicator[];
};

export type RubricMarkedGridIndicator = {
    id: RubricGridIndicator["id"];
    humanReadableMark: string | null;
    /** The selected descriptor ID, if any */
    descriptorId: RubricGridDescriptor["id"] | null;
};
