import type { ResponseStreamEvent, ResponseCompletedEvent } from "openai/resources/responses/responses";
import { sseApiRequest } from "@js/util/sbx/apiUtils";
import type { ApiBaseResponse } from "@js/util/sbx/apiUtils";

/** The final event that we should recieve in the SSE stream to indicate that the request finished */
const finalEventName: ResponseCompletedEvent["type"] = "response.completed";

export type ImageGeneratorApiPayload = {
    userPrompt: string;
    attributes: Record<string, string | null>;
    imageUrl: string | null;
    previousResponseId: string | null;
    /**
     * to keep track of the current ai conversation thread
     * on each load of the tab (in the dropzone modal) this will regen from the backend
     *
     * This should be unique for a new unique conversation
     */
    aiSessionId: string;
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
): Promise<ApiBaseResponse<ResponseCompletedEvent>> {
    return sseApiRequest<ResponseStreamEvent, ResponseCompletedEvent, ImageGeneratorApiPayload>({
        url,
        method: "POST",
        body,
        onMessage,
        finalEventName,
    });
}
