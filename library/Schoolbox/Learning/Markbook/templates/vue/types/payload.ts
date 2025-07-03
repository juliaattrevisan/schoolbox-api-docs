/**
 * The overall payload structure for the markbook data,
 * used for both the Course Markbook and the Class Markbook
 */

import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import type { MarkScheme, SubmissionErrorStates } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";

// Types for semantic clarity when referring to them later on...
type UserId = number;
export type ParticipantId = number;

export type MarkbookActivity = Pick<Activity, "id" | "title" | "assessmentType"> & {
    /** the readable type name of the activity, e.g. "Project", "Quiz", "Essay", "Homework", etc */
    typeName: string;
    /** for rendering the unit bars at the top of the page, course markbook only */
    unitSpec?: {
        id: number;
        title: string;
    };
    sequence: number;
    /** stats and the summary link are currently only relevant for specs */
    isSpecification: boolean;
    stats: {
        mean: string | null;
        draftMean: string | null;
    } | null;
    _links: Pick<NonNullable<Activity["_links"]>, "status" | "summary" | "modify" | "modifyContent"> | null;
};

export type MarkbookClassImplActivityParticipant = {
    isExcluded: boolean;
    hasSubmittedResponse: boolean;
    hasPublishedFeedback: boolean;
    progressTick: Activity["progressTick"] | null;
    marks: Record<
        "published" | "draft",
        {
            norm: number | null;
            hr: string | null;
            hrInstructor: string | null;
            comment: string | null;
            resubmissionRequired: boolean;
        }
    >;
    /**
     * Special data to allow for feedback submission
     * this isn't sent through with the initial payload, since currently that would potentially
     * require a lot of interaction with the Learnosity API for quizzes or other data which most markbook loads won't need for all activities.
     * Instead, the markbook popout will load this data when the user asks to create/edit feedback for a quiz or due work
     */
    extraInfo?: ActivityInfoResponsePayload;
    /** the following are only used the class markbook at the moment: */
    plan?: {
        isDynamic: boolean;
        name: string | null;
        dueDate: string | null;
    };
};

export type MarkbookClassImplActivity = {
    id: number;
    participants: Partial<Record<ParticipantId, MarkbookClassImplActivityParticipant>>;
    stats: {
        mean?: string | null;
        draftMean?: string | null;
        /** these will only ever be set on the class markbook at the moment: */
        sd?: string | null;
        min?: string | null;
        minNormalised?: number | null;
        max?: string | null;
        maxNormalised?: number | null;
        count?: number | null;
        quartile1: number | null;
        quartile2: number | null;
        quartile3: number | null;
        weight?: number | null;
        weightInProject?: number | null;
        sdNormalized: number | null;
        weightPercent?: number | null;
        projectWeightPercent?: number | null;
        markVisibility?: number;
    };
    _routes: {
        "learningAssessment.getStatus": string;
        student: Record<
            UserId,
            {
                "learningAssessmentTimeline.getFullTimeline": string | null;
                "learningDueWorkResponse.getParticularResponse": string | null;
                "learningDueWorkFeedback.postPublishFeedback": string | null;
                "learningAssessment.postExclude": string | null;
                "learningAssessment.postInclude": string | null;
                "learningAssessment.getSummary": string | null;
                "learningAssessment.getInfo": string | null;
                /* this will change depending on if it's a quiz or due work */
                postFeedback: string | null;
            }
        >;
    };
    instructorMarkScheme: MarkScheme;
    /* currently class markbook only */
    markVisibility?: number;
    courseManaged?: boolean | null;
    writable?: boolean;
    weight: number;
};

export type MarkbookClassImplStudent = {
    id: UserId;
    externalId: string | null;
    givenName: string;
    lastName: string;
    stats: {
        weightedMean: string | null;
        meanNormalised: number | null;
        draftWeightedMean: string | null;
        meanDraftNormalised: number | null;
    };
    _routes: {
        grades: string;
    };
};

export interface MarkbookClassImpl {
    folder: {
        id: number;
        title: string;
    };
    assessments: MarkbookClassImplActivity[];
    students: Record<UserId, MarkbookClassImplStudent>;
    /* overall stats for the entire class, currently only on the class markbook */
    stats: {
        normalisedMean: number;
        draftNormalisedMean: number;
        weightedMean: string;
        draftWeightedMean: string;
    } | null;
}

export interface Markbook {
    /** Student ranking based on the whole cohort/class  */
    rank: Record<UserId, number> | null;
    /** An overview of all the assessments that will be shown, in the order that they should be presented */
    assessments: Record<MarkbookActivity["id"], MarkbookActivity>;
    /**
     * Essentially groups of participants to be presented, representing a course implementation or folder
     * - For the course markbook, there is one of these for each course implementation
     * - For the class markbook, there is only ever one of these - the class folder itself
     */
    classImpls: MarkbookClassImpl[];
    /** Relevant routes */
    _routes: {
        exportReport: string | null;
        "courseImport.getImport": string | null;
    };
    /** Whether the user can access the markbook pop out */
    canAccessPopOut: boolean;
    /** A list of available visibilities, currently only used on the class markbook */
    availableVisibilities: number[];
    submissionErrorStates: SubmissionErrorStates;
}

/**
 * The payload returned by the learningAssessment.getInfo endpoint
 * This isn't fully defined yet, I have only pulled in what we actually use... there's more in the API response
 */
export type ActivityInfoResponsePayload = {
    maxMarks: number | null; // Not null for Quiz
    response: {
        id: number | null;
    };
    feedback: {
        // Not null for Due Work
        rubric: boolean | null;
        responseWithKamiDocs: boolean | null;
        fileCount: number | null;
    };
};

/** -------- other types -------- */

export type MarkbookPopoutSelectedCellInfo = {
    classImplId: number;
    studentId: UserId;
    activityId: MarkbookActivity["id"];
    referenceElement: HTMLTableCellElement;
};

export type MarkbookFeedbackSelectedCellInfo = {
    classImplId: number;
    studentId: UserId;
    activityId: MarkbookActivity["id"];
};
