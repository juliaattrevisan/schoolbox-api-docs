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
