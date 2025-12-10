import { defineStore } from "pinia";
import type { GalleryItem } from "lightgallery/lg-utils";
import { assertUnreachable } from "@js/util/errorUtils";
import { createLightbox } from "@js/image-lightbox/imageLightboxEvent";
import type { FileDropFile } from "@js/resource/fileDropTypes";
import type { AiSessionId } from "@schoolboxLibrary/Admin/OpenAI/Usage/templates/payload";
import { AgentChatMessageType } from "../agentChatTypes";
import type {
    AgentChatFeedMessage,
    AgentChatRequest,
    AgentChatResponse,
    AgentChatResponseInProgress,
    AiLogAgentChatData,
} from "../agentChatTypes";
import { AgentChatMessageEventType, generateImage } from "./agentChatPayload";
import type { AgentChatApiPayload } from "./agentChatPayload";

/**
 * Configuration for the request that is dependent on the place implementing Agent Chat
 */
export type AgentChatRequestConfig = {
    /** The route to send messages to */
    messageRoute: string;
    /** The AI session ID to include in the request, if required */
    aiSessionId: AiSessionId | null;
    /** Whether an image is required in responses */
    requireImage: boolean;
};

export const useAgentChatStore = defineStore("agentChat", () => {
    const feed = window.Vue.ref<AgentChatFeedMessage[]>([]);

    const activeGeneration = window.Vue.ref<{
        response: AgentChatResponseInProgress;
        abortController: AbortController;
    } | null>(null);

    /** When allowing selection of a particular entry (such as in the image generator), the selected entry */
    const selectedFeedEntry = window.Vue.ref<AgentChatFeedMessage["entryKey"] | null>(null);

    /**
     * @returns The full feed of messages (includes the partial entry at the end if currently generating)
     */
    const fullFeed = window.Vue.computed((): AgentChatFeedMessage[] => {
        if (activeGeneration.value === null) {
            return feed.value;
        }

        return [...feed.value, activeGeneration.value.response];
    });

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
     * Parse a previous feed from the log format
     * The results from this can then be used to populate the feed in the store or directly to <ac-feed>
     * @param logEntries - The log entries to import
     * @param user - The user who made the requests
     * @returns The parsed feed messages
     */
    function parseFeed(logEntries: AiLogAgentChatData[], user: AgentChatRequest["user"]): AgentChatFeedMessage[] {
        const parsedFeed: AgentChatFeedMessage[] = [];

        const messages = logEntries.flatMap((logMessages) => logMessages);
        for (const message of messages) {
            if (message.type === AgentChatMessageType.REQUEST) {
                parsedFeed.push({
                    type: AgentChatMessageType.REQUEST,
                    entryKey: getNextEntryKey(),
                    user,
                    payload: {
                        userPrompt: message.data,
                        attributes: {}, // not currently stored in logs
                        imageUrl: null, // not currently stored in logs
                        previousResponseId: null, // not currently stored in logs
                        aiSessionId: null, // not currently stored in logs
                    },
                    file: null, // not currently stored in logs
                } satisfies AgentChatRequest);
            } else if (message.type === AgentChatMessageType.RESPONSE) {
                parsedFeed.push({
                    ...message.data,
                    type: AgentChatMessageType.RESPONSE,
                    entryKey: getNextEntryKey(),
                } satisfies AgentChatResponse);
            } else {
                assertUnreachable(message);
            }
        }
        return parsedFeed;
    }

    /**
     * Import a previous feed into the store
     * @param messages - Messages to add to the feed
     */
    function importFeed(messages: AgentChatFeedMessage[]): void {
        for (const message of messages) {
            feed.value.push(message);
        }
    }

    /**
     * Reset the feed to an empty state
     */
    function resetFeed(): void {
        feed.value.length = 0;
        if (activeGeneration.value !== null) {
            activeGeneration.value.abortController.abort();
        }
        activeGeneration.value = null;
        selectedFeedEntry.value = null;
    }

    /**
     * Request a new image from the AI
     * @param config - The configuration for the request
     * @param payload - The payload to send to the AI
     * @param file - The file that was sent with the request (if any)
     * @returns whether the request was sent successfully or not
     */
    async function sendRequest(
        config: AgentChatRequestConfig,
        payload: AgentChatApiPayload,
        file: FileDropFile | null,
    ): Promise<boolean> {
        if (activeGeneration.value !== null) {
            return false;
        }

        feed.value.push({
            type: AgentChatMessageType.REQUEST,
            entryKey: getNextEntryKey(),
            user: {
                id: window.schoolboxUser.id,
                fullName: window.schoolboxUser.name,
                _links: {
                    profile: `/search/user/${window.schoolboxUser.id}`,
                    avatar: `/portrait.php?id=${window.schoolboxUser.id}&size=square64`,
                },
            },
            payload,
            file,
        });

        activeGeneration.value = {
            response: {
                type: AgentChatMessageType.RESPONSE,
                status: "in_progress",
                entryKey: `loading-${getNextEntryKey()}`,
                responseId: null,
                image: null,
                message: "",
            },
            abortController: new AbortController(),
        };

        let hasErrored = false;
        const response = await generateImage(
            config.messageRoute,
            payload,
            activeGeneration.value.abortController.signal,
            (event) => {
                if (event.data.success === false) {
                    /** Any arbitrary error has occured, could be a network error, server error on our side or the AI side, or something else */
                    hasErrored = true;
                    feed.value.push({
                        type: AgentChatMessageType.RESPONSE,
                        status: "error",
                        entryKey: getNextEntryKey(),
                        responseId: activeGeneration.value?.response.responseId ?? null,
                        message: event.data.message,
                    });
                    activeGeneration.value?.abortController.abort();
                    activeGeneration.value = null;
                    return;
                }

                if (activeGeneration.value === null) {
                    /** The generation was stopped before the stream fully stopped and we received something extra, ignore it  */
                    return;
                }

                if (event.eventType === AgentChatMessageEventType.CREATED) {
                    /** Usually the first message we will receive from the AI, take note of the response ID so we can re-use it later */
                    activeGeneration.value.response.responseId = event.data.data.responseId;
                } else if (event.eventType === AgentChatMessageEventType.PARTIAL_IMAGE) {
                    /** A partial image has been generated by the AI, show it! */
                    activeGeneration.value.response.image = event.data.data.image;
                } else if (event.eventType === AgentChatMessageEventType.DELTA) {
                    /** A delta of text output from the AI, append it to the current message to show the user that something is happening */
                    activeGeneration.value.response.message += event.data.data.delta;
                }

                /** Ignore any other message types, we don't need them (except for the final event, but apiUtils handles that) */
            },
        );

        if (activeGeneration.value === null) {
            // Already handled in the onMessage callback, or cancelled from somewhere else (i.e. resetting the feed)
            // we shouldn't add anything more to the feed
            return false;
        }

        const responseId = activeGeneration.value?.response.responseId ?? null;

        activeGeneration.value.abortController.abort();
        activeGeneration.value = null;

        if (hasErrored) {
            // Already handled in the onMessage callback
            return false;
        }

        if (response.success === false) {
            /**
             * Something failed that wasn't handled above, could be:
             * - The entire request (network?)
             * - The AI refused to generate likely due to OpenAI's moderation system, we can't do anything about that
             * - The AI returned an incomplete response, such as if max output tokens were reached
             * - Some other unknown error
             */
            feed.value.push({
                type: AgentChatMessageType.RESPONSE,
                status: "error",
                entryKey: getNextEntryKey(),
                responseId,
                message: response.message,
            });
            return false;
        }

        /**
         * The image generator requires an image and uses the scenario where no image is returned
         * as a moderation error. That *should* be handled already by the backend and returned as a type: error
         * so we should never actually get here but just in case, check for it to avoid confusion and bad state
         */
        if (config.requireImage && response.data.image === null) {
            feed.value.push({
                type: AgentChatMessageType.RESPONSE,
                status: "error",
                entryKey: getNextEntryKey(),
                responseId: response.data.responseId,
                message: response.data.message,
            });
            return false;
        }

        /** All good at this point, persist the final response in the feed */
        feed.value.push({
            type: AgentChatMessageType.RESPONSE,
            status: "success",
            entryKey: getNextEntryKey(),
            responseId: response.data.responseId,
            image: response.data.image,
            message: response.data.message,
        });

        /** Update the selected feed entry to the latest one, doesn't do anything if the implementation doesn't use this function */
        selectedFeedEntry.value = feed.value[feed.value.length - 1].entryKey;

        return true;
    }

    /**
     * Show a lightbox with all the images in the feed
     * @param entryKey - The entry key that should be selected initially
     */
    async function spawnLightbox(entryKey: AgentChatFeedMessage["entryKey"]): Promise<void> {
        // only show successful responses that contain images
        const finalFeedItems = feed.value.filter(
            (item) => item.type === AgentChatMessageType.RESPONSE && item.status === "success",
        );

        const slides: GalleryItem[] = [];
        let selectedIndex = 0;

        for (const item of finalFeedItems) {
            if (item.image) {
                slides.push({ src: `data:${item.image.mime};base64,${item.image.b64}` });

                if (item.entryKey === entryKey) {
                    selectedIndex = slides.length - 1;
                }
            }
        }

        const lightbox = await createLightbox("ac-feed-lightbox", slides, {
            index: selectedIndex,
        });
        lightbox.openGallery();
    }

    return {
        feed,
        fullFeed,
        parseFeed,
        resetFeed,
        importFeed,
        activeGeneration,
        getNextEntryKey,
        getPreviousResponseId,
        sendRequest,
        selectedFeedEntry,
        spawnLightbox,
    };
});
