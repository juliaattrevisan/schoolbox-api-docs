import { defineStore } from "pinia";
import type { AiFeedMessage, ImageResponseInProgress } from "../imageGeneratorTypes";
import { generateImage } from "./imageGeneratorPayload";
import type { ImageGeneratorApiPayload } from "./imageGeneratorPayload";

export const useImageGeneratorStore = defineStore("imageGenerator", () => {
    const feed = window.Vue.ref<AiFeedMessage[]>([]);
    const generateRoute = window.Vue.ref<string | null>(null);

    const activeGeneration = window.Vue.ref<ImageResponseInProgress | null>(null);

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

    const currentEntryKey = window.Vue.ref<number>(0);

    /**
     * Any new entry in the feed must have a unique entry key
     * This ensures that Vue can keep track of each individual entry correctly
     * @returns The next entry key
     */
    function getNextEntryKey(): number {
        currentEntryKey.value += 1;
        return currentEntryKey.value;
    }

    /**
     * Request a new image from the AI
     * @param payload - The payload to send to the AI
     * @returns whether the request was sent successfully or not
     */
    async function sendRequest(payload: ImageGeneratorApiPayload): Promise<boolean> {
        if (activeGeneration.value !== null) {
            return false;
        }

        if (generateRoute.value === null) {
            throw new Error("generateRoute is not set");
        }

        feed.value.push({
            type: "request",
            entryKey: getNextEntryKey(),
            user: window.schoolboxUser,
            prompt: payload.userPrompt,
            attributes: payload.attributes,
            previousResponseId: payload.previousResponseId,
        });

        activeGeneration.value = {
            type: "response",
            status: "in_progress",
            entryKey: `loading-${getNextEntryKey()}`,
            responseId: null,
            src: null,
            message: "",
        };

        let hasErrored = false;
        const response = await generateImage(generateRoute.value, payload, (message) => {
            if (message.success === false) {
                hasErrored = true;
                feed.value.push({
                    type: "response",
                    status: "error",
                    entryKey: getNextEntryKey(),
                    responseId: activeGeneration.value?.responseId ?? null,
                    message: message.message,
                });
                activeGeneration.value = null;
                return;
            }

            if (activeGeneration.value === null) {
                return;
            }

            if (message.data.type === "response.created") {
                activeGeneration.value.responseId = message.data.response.id;
            } else if (message.data.type === "response.image_generation_call.partial_image") {
                activeGeneration.value.src = `data:image/png;base64,${message.data.partial_image_b64}`;
            } else if (message.data.type === "response.output_text.delta") {
                activeGeneration.value.message += message.data.delta;
            }
        });

        const responseId = activeGeneration.value?.responseId ?? null;
        activeGeneration.value = null;

        if (hasErrored) {
            // Already handled in the onMessage callback
            return false;
        }

        if (response.success === false) {
            feed.value.push({
                type: "response",
                status: "error",
                entryKey: getNextEntryKey(),
                responseId,
                message: response.message,
            });
            return false;
        }

        const outputImage = response.data.response.output.filter((item) => item.type === "image_generation_call")[0];
        const outputMessage = response.data.response.output.filter((item) => item.type === "message")[0];
        const outputContent = outputMessage?.content[0] ?? null;

        if (outputImage === null) {
            throw new Error("No output image found in response");
        }

        if (outputContent === null) {
            throw new Error("No output content found in response");
        }

        if (outputContent.type === "refusal") {
            feed.value.push({
                type: "response",
                status: "error",
                entryKey: getNextEntryKey(),
                responseId: response.data.response.id,
                message: outputContent.refusal,
            });
            return false;
        }

        feed.value.push({
            type: "response",
            status: "success",
            entryKey: getNextEntryKey(),
            responseId: response.data.response.id,
            src: `data:image/png;base64,${outputImage.result}`,
            message: outputContent.text,
        });

        selectedFeedEntry.value = feed.value[feed.value.length - 1].entryKey;

        return true;
    }

    return {
        init,
        feed,
        activeGeneration,
        getPreviousResponseId,
        sendRequest,
        selectedFeedEntry,
    };
});
