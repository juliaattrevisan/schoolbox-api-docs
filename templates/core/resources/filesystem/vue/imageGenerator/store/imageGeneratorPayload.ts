import type { ResponseStreamEvent } from "openai/resources/responses/responses";
import { sseApiRequest } from "@js/util/sbx/apiUtils";
import type { ApiBaseResponse } from "@js/util/sbx/apiUtils";


/** The "complete" response we expect to receive at the very end from the API */
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
 * @param body - The data to send in the request
 * @param onMessage - The callback to handle each message from the server
 * @returns The response from the fetch
 */
export async function generateImage(
    url: string,
    body: ImageGeneratorApiPayload,
    onMessage: (response: ApiBaseResponse<ResponseStreamEvent>) => void,
): Promise<ApiBaseResponse<ImageGeneratorApiResponse>> {
    return sseApiRequest<ResponseStreamEvent, ImageGeneratorApiResponse, ImageGeneratorApiPayload>({
        url,
        method: "POST",
        body,
        onMessage,
    });
}
