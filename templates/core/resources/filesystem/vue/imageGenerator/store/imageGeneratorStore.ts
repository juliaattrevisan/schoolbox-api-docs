import { defineStore } from "pinia";
import type { AiFeedMessage } from "../imageGeneratorTypes";
import { generateImage } from "./imageGeneratorPayload";
import type { ImageGeneratorApiPayload } from "./imageGeneratorPayload";

export const useImageGeneratorStore = defineStore("imageGenerator", () => {
    const feed = window.Vue.ref<AiFeedMessage[]>([]);

    const isGenerating = window.Vue.ref<boolean>(false);
    const generateError = window.Vue.ref<string | null>(null);

    const selectedFeedEntry = window.Vue.ref<AiFeedMessage["entryKey"] | null>(null);

    /**
     * @returns The previous response ID from the feed if it exists
     */
    function getPreviousResponseId(): string | null {
        const responses = feed.value.filter((item) => item.type === "response");
        if (responses.length === 0) {
            return null;
        }
        return responses[responses.length - 1].responseId;
    }

    /**
     * Request a new image from the AI
     * @param payload - The payload to send to the AI
     * @returns whether the request was sent successfully or not
     */
    async function sendRequest(payload: ImageGeneratorApiPayload): Promise<boolean> {
        if (isGenerating.value) {
            return false;
        }

        isGenerating.value = true;
        generateError.value = null;

        feed.value.push({
            type: "request",
            entryKey: feed.value.length + 1,
            user: window.schoolboxUser,
            prompt: payload.userPrompt,
            imageSize: payload.imageSize,
            imageStyle: payload.imageStyle,
            previousResponseId: payload.previousResponseId,
        });

        // TODO: Pass thru route
        const response = await generateImage("/openai/image", payload);

        isGenerating.value = false;
        if (response.success === false) {
            feed.value.push({
                type: "response",
                status: "error",
                entryKey: feed.value.length + 1,
                responseId: response.responseId,
                message: response.message,
            });
            return false;
        }

        feed.value.push({
            type: "request",
            user: window.schoolboxUser,
            prompt: payload.userPrompt,
            imageSize: payload.imageSize,
            imageStyle: payload.imageStyle,
            previousResponseId: payload.previousResponseId,
        });

        const image = response.data.output.find((item) => item.type === "image_generation_call");
        const message = response.data.output.find((item) => item.type === "message");
        const messageContent = message ? message.content.find((c) => c.type === "output_text") : null;

        // TODO: Handle the response data properly in the backend instead of here
        feed.value.push({
            type: "response",
            status: "success",
            entryKey: feed.value.length + 1,
            responseId: response.data.id,
            src: `data:image/png;base64,${image.result}`,
            message: messageContent ? messageContent.text.trim() : "",
        });

        selectedFeedEntry.value = feed.value[feed.value.length - 1].entryKey;

        return true;
    }

    return {
        feed,
        isGenerating,
        generateError,
        getPreviousResponseId,
        sendRequest,
        selectedFeedEntry,
    };
});
