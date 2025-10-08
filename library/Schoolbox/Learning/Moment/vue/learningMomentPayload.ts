import { apiRequest } from "@js/util/sbx/apiUtils";
import type { ApiBaseResponse } from "@js/util/sbx/apiUtils";
import type { LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";

// PUT learningMomentEvidence.putPublish

type LearningMomentPublishApiPayload = {
    /** Soft-delete all the comments associated with this learning moment */
    deleteComments?: boolean;
};

type LearningMomentPublishApiResponse = unknown; // always redirects to the profile page at the moment...

/**
 * Publish a learning moment
 * @param putPublishUrl learningMomentEvidence.putPublish
 * @param body the request body
 * @returns response
 */
export function publishLearningMoment(
    putPublishUrl: string,
    body: LearningMomentPublishApiPayload,
): Promise<ApiBaseResponse<LearningMomentPublishApiResponse>> {
    return apiRequest<LearningMomentPublishApiResponse, LearningMomentPublishApiPayload>({
        url: putPublishUrl,
        method: "PUT",
        body,
    });
}

// PUT learningMomentEvidence.putBulkPublish

type LearningMomentBulkPublishApiPayload = LearningMomentPublishApiPayload & {
    momentIds: LearningMoment["id"][];
};

type LearningMomentBulkPublishApiResponse = Record<LearningMoment["id"], LearningMoment>;

/**
 * Bulk publish multiple learning moments
 * @param putBulkPublishUrl learningMomentEvidence.putBulkPublish
 * @param body the request body
 * @returns response
 */
export function bulkPublishLearningMoments(
    putBulkPublishUrl: string,
    body: LearningMomentBulkPublishApiPayload,
): Promise<ApiBaseResponse<LearningMomentBulkPublishApiResponse>> {
    return apiRequest<LearningMomentBulkPublishApiResponse, LearningMomentBulkPublishApiPayload>({
        url: putBulkPublishUrl,
        method: "PUT",
        body,
    });
}

// PUT learningMomentEvidence.putReject

type LearningMomentRejectApiPayload = {
    rejectionReason: string;
};

type LearningMomentRejectApiResponse = unknown; // always redirects to the profile page at the moment...

/**
 * Reject a learning moment
 * @param putRejectUrl learningMomentEvidence.putReject
 * @param body the request body
 * @returns response
 */
export function rejectLearningMoment(
    putRejectUrl: string,
    body: LearningMomentRejectApiPayload,
): Promise<ApiBaseResponse<LearningMomentRejectApiResponse>> {
    return apiRequest<LearningMomentRejectApiResponse, LearningMomentRejectApiPayload>({
        url: putRejectUrl,
        method: "PUT",
        body,
    });
}

// PUT learningMomentEvidence.putBulkReject

type LearningMomentBulkRejectApiPayload = LearningMomentRejectApiPayload & {
    momentIds: LearningMoment["id"][];
};

type LearningMomentBulkRejectApiResponse = Record<LearningMoment["id"], LearningMoment>;

/**
 * Bulk reject multiple learning moments
 * @param putBulkRejectUrl learningMomentEvidence.putBulkReject
 * @param body the request body
 * @returns response
 */
export function bulkRejectLearningMoments(
    putBulkRejectUrl: string,
    body: LearningMomentBulkRejectApiPayload,
): Promise<ApiBaseResponse<LearningMomentBulkRejectApiResponse>> {
    return apiRequest<LearningMomentBulkRejectApiResponse, LearningMomentBulkRejectApiPayload>({
        url: putBulkRejectUrl,
        method: "PUT",
        body,
    });
}
