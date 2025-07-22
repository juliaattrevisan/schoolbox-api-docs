import { defineStore } from "pinia";
import type { AiFeedMessage, ImageResponseSuccess } from "../imageGeneratorTypes";
import { generateImage } from "./imageGeneratorPayload";
import type { ImageGeneratorApiPayload } from "./imageGeneratorPayload";

export const useImageGeneratorStore = defineStore("imageGenerator", () => {
    const feed = window.Vue.ref<AiFeedMessage[]>([]);
    const generateRoute = window.Vue.ref<string | null>(null);

    const isGenerating = window.Vue.ref<boolean>(false);

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
     * @returns The next entry key for adding a new item to the feed
     */
    function getNextEntryKey(): number {
        const responses = feed.value.filter((item) => "entryKey" in item).filter((item) => item.entryKey !== null);
        if (responses.length === 0) {
            return 1;
        }
        return responses[responses.length - 1].entryKey + 1;
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

        feed.value.push({
            type: "request",
            entryKey: getNextEntryKey(),
            user: window.schoolboxUser,
            prompt: payload.userPrompt,
            attributes: payload.attributes,
            previousResponseId: payload.previousResponseId,
        });

        let responseEntry: ImageResponseSuccess | null = null;

        const response = await generateImage(generateRoute.value, payload, (message) => {
            if (message.success === false) {
                // TODO
                console.error(message);
                isGenerating.value = false;
                return;
            }

            if (message.data.type === "response.image_generation_call.partial_image") {
                const newSrc = `data:image/png;base64,${message.data.partial_image_b64}`;

                if (responseEntry === null) {
                    const newResponseEntry: ImageResponseSuccess = {
                        type: "response",
                        status: "success",
                        entryKey: getNextEntryKey(),
                        responseId: message.data.item_id,
                        src: newSrc,
                        message: "",
                    };

                    responseEntry = newResponseEntry;
                    feed.value.push(newResponseEntry);
                } else {
                    const newResponseEntry = {
                        ...responseEntry,
                        entryKey: getNextEntryKey(),
                        src: newSrc,
                    };

                    responseEntry = newResponseEntry;
                    feed.value.pop();
                    feed.value.push(newResponseEntry);
                }
            } else if (message.data.type === "response.output_text.delta") {
                if (responseEntry === null || responseEntry.message === null) {
                    // TODO
                    throw new Error("no response entry yet");
                }

                const newResponseEntry = {
                    ...responseEntry,
                    entryKey: getNextEntryKey(),
                    message: `${responseEntry.message}${message.data.delta}`,
                };

                responseEntry = newResponseEntry;
                feed.value.pop();
                feed.value.push(newResponseEntry);
            }
        });

        isGenerating.value = false;
        if (response.success === false) {
            feed.value.push({
                type: "response",
                status: "error",
                entryKey: getNextEntryKey(),
                responseId: null,
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
        isGenerating,
        getPreviousResponseId,
        sendRequest,
        selectedFeedEntry,
    };
});
