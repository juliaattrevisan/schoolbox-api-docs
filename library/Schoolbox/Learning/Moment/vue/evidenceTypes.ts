import type { StorageFile } from "@js/resource/resourceUtils";
import type { Metadata } from "@js/util/sbx/apiUtils";
import type { SelectizeSelectorItem } from "@js/vendors/selectize/selectizeSelectorTypes";
import type { Node } from "@schoolboxCurriculum/types/Node";
import type { UserAuthor } from "@schoolboxLibrary/types/User";

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

/**
 * A learning moment
 * @see /library/Schoolbox/Learning/Moment/Translator/ModelToArray/Base.php
 */
export type LearningMoment = {
    id: number;
    body: string | null;
    createdAt: string | null;
    updatedAt: string | null;
    creator: UserAuthor | null;
    recipient: UserAuthor | null;
    currentState: EvidenceStatus;
    folders:
        | {
              id: number | string;
              name: string;
              code: string;
          }[]
        | null;
    curriculumNodes: Pick<Node, "id" | "name" | "code">[] | null;
    discussionThread: null | {
        children: {
            data: unknown; // we don't use this property here yet
            metadata: Metadata;
        };
    };
    evidence: {
        storage: StorageFile;
        filename: string;
    }[];
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
