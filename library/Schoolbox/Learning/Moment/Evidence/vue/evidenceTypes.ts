/** The current state of a learning moment, see /library/Schoolbox/Learning/Moment/Evidence/Model.php */
export enum EvidenceStatus {
    DRAFT = "draft",
    SUBMITTED = "submitted",
    PUBLISHED = "published",
    REJECTED = "rejected",
}

/** Labels to use when displaying each evidence status */
export const evidenceStatusLabels: Record<EvidenceStatus, string> = {
    [EvidenceStatus.DRAFT]: "Draft",
    [EvidenceStatus.SUBMITTED]: "Requiring approval",
    [EvidenceStatus.PUBLISHED]: "Published",
    [EvidenceStatus.REJECTED]: "Requiring update",
};

export type LearningMoment = {
    // NOTE: this doesn't have all properties yet, see library/Schoolbox/Learning/Moment/Evidence/Translator/ModelToArray/Base.php
    id: number;
    body: string;
    currentState: EvidenceStatus;
    _links: {
        // these are injected by certain endpoints...
        publish?: string | null;
        reject?: string | null;
        submit?: string | null;
    };
};
