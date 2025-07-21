import { apiRequest } from "@js/util/sbx/apiUtils";
import type { ApiBaseResponse } from "@js/util/sbx/apiUtils";

/** The success response we expect to receive from the API */
type ImageGeneratorApiResponse = {
    responseId: string;
    src: string;
    message: string | null;
};

export type ImageGeneratorApiPayload = {
    userPrompt: string;
    attributes: Record<string, string | null>;
    previousResponseId: string | null;
};

/**
 * Generate an image using the image generation API
 * @param url - The endpoint to fetch
 * @param payload - The data to send in the request
 * @returns The response from the fetch
 */
export async function generateImage(
    url: string,
    payload: ImageGeneratorApiPayload,
): Promise<ApiBaseResponse<ImageGeneratorApiResponse>> {
    const response = await apiRequest<ImageGeneratorApiResponse, ImageGeneratorApiPayload>({
        url,
        method: "POST",
        body: payload,
    });

    return response;
}
