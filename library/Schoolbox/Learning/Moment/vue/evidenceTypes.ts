import type { StorageFile } from "@js/resource/resourceUtils";
import type { Metadata } from "@js/util/sbx/apiUtils";
import type { SelectizeSelectorItem } from "@js/vendors/selectize/selectizeSelectorTypes";
import type { Node } from "@schoolboxCurriculum/types/Node";

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
    // NOTE: this doesn't have all properties yet, see library/Schoolbox/Learning/Moment/Translator/ModelToArray/Base.php
    id: number;
    body: string | null;
    currentState: EvidenceStatus;
    curriculumNodes: Pick<Node, "id" | "name" | "code">[] | null;
    discussionThread: null | {
        children: {
            data: unknown; // we don't use this property here yet
            metadata: Metadata;
        };
    };
    evidence: StorageFile[];
    tags: SelectizeSelectorItem[] | null;
    _links: {
        createThread?: string | null;
        detail?: string | null;
        modifyForm?: string | null;
        put?: string | null;
        draft?: string | null;
        publish?: string | null;
        reject?: string | null;
        submit?: string | null;
        delete?: string | null;
    };
};
