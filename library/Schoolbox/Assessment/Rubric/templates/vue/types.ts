import type { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";

/**
 * @see /library/Schoolbox/Assessment/Rubric/Grid/Translator/ModelToArray.php
 */
export type RubricGrid = {
    id: number; // currently the assessment ID, or 0 for new rubrics
    title: string;
    weight: number | null;
    useMarkRange: boolean;
    levels: RubricGridLevel[];
    capabilities: RubricGridCapability[];
    capabilityMarkType: MarkTypeSlug | null;
    capabilityGradeGroup: number | null;
};

export type RubricGridLevel = {
    id: number;
    name: string;
};

export type RubricGridCapability = {
    id: number;
    name: string;
    /**
     * Is this capability in use on any existing assessment,
     * excluding the current rubric grid's assessment?
     */
    inUseExcludingAssessment: boolean;
    maxValue: number | null;
    indicators: RubricGridIndicator[];
};

export type RubricGridIndicator = {
    id: number;
    name: string;
    /**
     * Is this indicator in use on any existing assessment,
     * excluding the current rubric grid's assessment?
     */
    inUseExcludingAssessment: boolean;
    hasUsage: boolean;
    /**
     * on the rubric builder, descriptors won't be null as missing ones prefill with empty values,
     * but when loading a rubric grid for marking/viewing, null is used to identify missing descriptors instead.
     *
     * TODO: we could consider letting the rubric builder mutate the input data after loading so that it recieves null
     * but still handles reactivity correctly, but for until we refine these structs this is simpler for now.
     */
    descriptors: (RubricGridDescriptor | null)[];
};

export type RubricGridDescriptor = {
    id: number;
    description: string;
    maxValue: number | null;
};

export type ResultSet = {
    id: number;
    results: { value: number; label: string }[];
};

// TODO: refine RubricMarkedGrid/associated types with the above/the API/etc

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
    /** matches grid levels, null where not applicable */
    descriptors: (RubricMarkedGridDescriptor | null)[];
};

export type RubricMarkedGridDescriptor = {
    id: RubricGridDescriptor["id"];
    selected: boolean;
    value:
        | { left: number | null; right: number | null } // if using mark range
        | { max: number | null };
};

/**
 * maxCapabilityLength/maxIndicatorLength/maxDescriptorLength is 900, due to the way
 * browsers look at maxlength as character lenght, but mysql table looks at char byte length so
 * if there are bullet points in the textbox they count as 3 characters, and will cause issues,
 * so lets work on 900 as the limit, and we shouldn't hit the db max..
 * @see /library/Schoolbox/Assessment/Rubric/DataModel/Capability/Mapper.php CAPABILITY_NAME_MAX_CHARS_TO_STORE
 * @see also SA-13689
 */
export const MAX_CAPABILITY_LENGTH = 900;
export const MAX_INDICATOR_LENGTH = 900;
export const MAX_DESCRIPTOR_LENGTH = 900;
