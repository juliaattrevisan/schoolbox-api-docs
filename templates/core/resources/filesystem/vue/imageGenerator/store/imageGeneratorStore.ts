import { defineStore } from "pinia";
import type { AiFeedMessage } from "../imageGeneratorTypes";
import { generateImage } from "./imageGeneratorPayload";
import type { ImageGeneratorApiPayload } from "./imageGeneratorPayload";

export const useImageGeneratorStore = defineStore("imageGenerator", () => {
    const feed = window.Vue.ref<AiFeedMessage[]>([]);
    const generateRoute = window.Vue.ref<string | null>(null);

    const isGenerating = window.Vue.ref<boolean>(false);
    const generateError = window.Vue.ref<string | null>(null);

    const selectedFeedEntry = window.Vue.ref<AiFeedMessage["entryKey"] | null>(null);

    /**
     * Initialise the store
     * @param $generateRoute - The route to use for generating images
     */
    function init($generateRoute: string): void {
        generateRoute.value = $generateRoute;
    }

    /**
     * @returns The previous response ID from the feed if it exists
     */
    function getPreviousResponseId(): string | null {
        const responses = feed.value.filter((item) => "responseId" in item).filter((item) => item.responseId !== null);
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

        if (generateRoute.value === null) {
            throw new Error("generateRoute is not set");
        }

        isGenerating.value = true;
        generateError.value = null;

        feed.value.push({
            type: "request",
            entryKey: feed.value.length + 1,
            user: window.schoolboxUser,
            prompt: payload.userPrompt,
            attributes: payload.attributes,
            previousResponseId: payload.previousResponseId,
        });

        const response = await generateImage(generateRoute.value, payload);

        isGenerating.value = false;
        if (response.success === false) {
            feed.value.push({
                type: "response",
                status: "error",
                entryKey: feed.value.length + 1,
                responseId: null,
                message: response.message,
            });
            return false;
        }

        feed.value.push({
            type: "response",
            status: "success",
            entryKey: feed.value.length + 1,
            ...response.data,
        });

        selectedFeedEntry.value = feed.value[feed.value.length - 1].entryKey;

        return true;
    }

    return {
        init,
        feed,
        isGenerating,
        generateError,
        getPreviousResponseId,
        sendRequest,
        selectedFeedEntry,
    };
});
