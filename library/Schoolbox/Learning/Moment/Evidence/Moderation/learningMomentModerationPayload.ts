import { apiRequest } from "@js/util/sbx/apiUtils";
import type { ApiBaseResponse } from "@js/util/sbx/apiUtils";

// PUT learningMomentModeration.putPublish

type LearningMomentPublishApiPayload = {
    /** Soft-delete all the comments associated with this learning moment */
    deleteComments?: boolean;
};

type LearningMomentPublishApiResponse = unknown;

/**
 * Publish a learning moment
 * @param putPublishUrl learningMomentModeration.putPublish
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

// PUT learningMomentModeration.putBulkPublish

type LearningMomentBulkPublishApiPayload = LearningMomentPublishApiPayload & {
    momentIds: number[];
};

type LearningMomentBulkPublishApiResponse = unknown;

/**
 * Bulk publish multiple learning moments
 * @param putBulkPublishUrl learningMomentModeration.putBulkPublish
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

// PUT learningMomentModeration.putReject

type LearningMomentRejectApiPayload = {
    rejectionReason: string;
};

type LearningMomentRejectApiResponse = unknown;

/**
 * Reject a learning moment
 * @param putRejectUrl learningMomentModeration.putReject
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

// PUT learningMomentModeration.putBulkReject

type LearningMomentBulkRejectApiPayload = LearningMomentRejectApiPayload & {
    momentIds: number[];
};

type LearningMomentBulkRejectApiResponse = unknown;

/**
 * Bulk reject multiple learning moments
 * @param putBulkRejectUrl learningMomentModeration.putBulkReject
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
