export type RubricGrid = {
    id: number; // currently the assessment ID, or 0 for new rubrics
    levels: RubricGridLevel[];
    capabilities: RubricGridCapability[];
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
    indicators: unknown[];
};

/**
 * maxCapabilityLength/maxIndicatorLength/maxDescriptorLength is 900, due to the way browsers look at maxlength as character lenght, but mysql table looks at char byte length
 * so if there are bullet points in the textbox they count as 3 characters, and will cause issues, so lets work on 900 as the limit, and we shouldn't hit the db max..
 */
export const MAX_CAPABILITY_LENGTH = 900;
export const MAX_INDICATOR_LENGTH = 900;
export const MAX_DESCRIPTOR_LENGTH = 900;
