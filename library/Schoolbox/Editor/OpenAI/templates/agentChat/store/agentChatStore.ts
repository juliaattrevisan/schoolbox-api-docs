import { defineStore } from "pinia";
import type { GalleryItem } from "lightgallery/lg-utils";
import { useEventBus } from "@vueuse/core";
import { getRfc3339String } from "@js/util/dateUtils";
import { assertUnreachable } from "@js/util/errorUtils";
import { createLightbox } from "@js/image-lightbox/imageLightboxEvent";
import type { FileDropFile } from "@js/resource/fileDropTypes";
import type { AiSessionId } from "@schoolboxLibrary/Admin/OpenAI/Usage/templates/payload";
import { acPromptEventKey } from "../agentChatSymbols";
import { AGENT_CHAT_MAX_PROMPT_CHARS, AgentChatGuardrailSeverity, AgentChatMessageType } from "../agentChatTypes";
import type { AgentChatFeedMessage, AnyAgentChatFeedMessage, AgentChatLogEntry } from "../agentChatTypes";
import { AgentChatMessageEventType, sendMessage } from "./agentChatPayload";
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
    const feed = window.Vue.ref<AnyAgentChatFeedMessage[]>([]);
    const acPromptEventBus = useEventBus(acPromptEventKey);

    const activeGeneration = window.Vue.ref<{
        response: AgentChatFeedMessage<AgentChatMessageType.RESPONSE>;
        abortController: AbortController;
    } | null>(null);

    /** When allowing selection of a particular entry (such as in the image generator), the selected entry */
    const selectedFeedEntry = window.Vue.ref<AnyAgentChatFeedMessage["entryKey"] | null>(null);

    /**
     * @returns The full feed of messages (includes the partial entry at the end if currently generating)
     */
    const fullFeed = window.Vue.computed((): AnyAgentChatFeedMessage[] => {
        if (activeGeneration.value === null) {
            return feed.value;
        }

        return [...feed.value, activeGeneration.value.response];
    });

    /**
     * @returns The previous response ID from the feed if it exists
     */
    function getPreviousResponseId(): string | null {
        const responseIds = feed.value
            .map((item) => {
                if (typeof item.data !== "object" || item.data === null) {
                    return null; // likely a request, as .data is a string there
                }

                return "responseId" in item.data ? item.data.responseId : null;
            })
            .filter((id) => id !== null);

        if (responseIds.length === 0) {
            return null;
        }

        return responseIds[responseIds.length - 1];
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
     * Parse a log entry into the format of a feed message
     * @param logEntry - The log entry to parse
     * @param user - The user who made the request
     * @template TType - The type of message being parsed
     * @returns The entry, converted into a message to use in the feed
     */
    function parseFeedEntry(
        logEntry: AgentChatLogEntry,
        user: AgentChatFeedMessage<AgentChatMessageType.REQUEST>["meta"]["user"],
    ): AnyAgentChatFeedMessage {
        const message = { ...logEntry, entryKey: getNextEntryKey() };

        if (message.type === AgentChatMessageType.REQUEST) {
            return {
                ...message,
                meta: {
                    user,
                    payload: {
                        userPrompt: message.data,
                        attributes: {}, // not currently stored in logs
                        imageUrl: null, // not currently stored in logs
                        previousResponseId: null, // not currently stored in logs
                        aiSessionId: null, // not currently stored in logs
                    },
                    file: null, // not currently stored in logs
                },
            } satisfies AgentChatFeedMessage<AgentChatMessageType.REQUEST>;
        }

        if (message.type === AgentChatMessageType.RESPONSE) {
            return {
                ...message,
                meta: null,
            } satisfies AgentChatFeedMessage<AgentChatMessageType.RESPONSE>;
        }

        if (message.type === AgentChatMessageType.ALERT) {
            return {
                ...message,
                meta: null,
            } satisfies AgentChatFeedMessage<AgentChatMessageType.ALERT>;
        }

        return assertUnreachable(message);
    }

    /**
     * Parse a previous feed from the log format
     * The results from this can then be used to populate the feed in the store or directly to <ac-feed>
     * @param logEntries - The log entries to import
     * @param user - The user who made the requests
     * @returns The parsed feed messages
     */
    function parseFeed(
        logEntries: AgentChatLogEntry[],
        user: AgentChatFeedMessage<AgentChatMessageType.REQUEST>["meta"]["user"],
    ): AnyAgentChatFeedMessage[] {
        // const parsedFeed: AgentChatFeedMessage[] = [];

        const messages = [...logEntries].sort((a, b) => {
            const dateA = new Date(a.createdAt).getTime();
            const dateB = new Date(b.createdAt).getTime();
            return dateA - dateB;
        });

        return messages.map((logEntry) => parseFeedEntry(logEntry, user));
    }

    /**
     * Import a previous feed into the store
     * @param messages - Messages to add to the feed
     */
    function importFeed(messages: AnyAgentChatFeedMessage[]): void {
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

    // overloads for getLatestFeedEntry
    function getLatestFeedEntry(type: null): AnyAgentChatFeedMessage | null;
    function getLatestFeedEntry<TType extends AgentChatMessageType>(type: TType): AgentChatFeedMessage<TType> | null;

    /**
     * Retrieve the latest feed entry with a given type
     * @param type - The type of message to find
     * @template TType - To derive the actual return type based on the input type, if any
     * @returns The latest message with the given type, or null if none found
     */
    function getLatestFeedEntry(type: AgentChatMessageType | null): AnyAgentChatFeedMessage | null {
        for (let i = feed.value.length - 1; i >= 0; i -= 1) {
            const message = feed.value[i];
            if (type === null || type === message.type) {
                return message;
            }
        }

        return null;
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

        const authorInfo = {
            id: window.schoolboxUser.id,
            fullName: window.schoolboxUser.name,
            _links: {
                profile: `/search/user/${window.schoolboxUser.id}`,
                avatar: `/portrait.php?id=${window.schoolboxUser.id}&size=square64`,
            },
        };

        feed.value.push({
            type: AgentChatMessageType.REQUEST,
            data: payload.userPrompt,
            meta: { user: authorInfo, payload, file },
            entryKey: getNextEntryKey(),
            createdAt: getRfc3339String(new Date()),
        });

        /** final chance before hitting the CharacterLimit guardrail, make sure the request doesn't exceed the limit */
        if (payload.userPrompt.length > AGENT_CHAT_MAX_PROMPT_CHARS) {
            // someone should only get here if they edited the textarea's max length attribute manually
            feed.value.push({
                type: AgentChatMessageType.ALERT,
                data: {
                    side: AgentChatMessageType.RESPONSE,
                    severity: AgentChatGuardrailSeverity,
                    message: `Your message exceeds the maximum allowed length of ${AGENT_CHAT_MAX_PROMPT_CHARS} characters. Please shorten your message and try again.`,
                },
                meta: null,
                entryKey: getNextEntryKey(),
                createdAt: getRfc3339String(new Date()),
            });
            return false;
        }

        activeGeneration.value = {
            response: {
                type: AgentChatMessageType.RESPONSE,
                data: {
                    status: "in_progress",
                    responseId: null,
                    image: null,
                    message: "",
                },
                meta: null,
                entryKey: getNextEntryKey(),
                createdAt: getRfc3339String(new Date()),
            },
            abortController: new AbortController(),
        };

        let hasErrored = false;
        const response = await sendMessage(
            config.messageRoute,
            payload,
            activeGeneration.value.abortController.signal,
            (event) => {
                if (event.data.success === false) {
                    /** Any arbitrary error has occured, could be a network error, server error on our side or the AI side, or something else */
                    hasErrored = true;
                    feed.value.push({
                        type: AgentChatMessageType.RESPONSE,
                        data: {
                            status: "error",
                            responseId: activeGeneration.value?.response.data.responseId ?? null,
                            message: event.data.message,
                        },
                        meta: null,
                        entryKey: getNextEntryKey(),
                        createdAt: getRfc3339String(new Date()),
                    });
                    activeGeneration.value?.abortController.abort();
                    activeGeneration.value = null;
                    return;
                }

                if (event.eventType === AgentChatMessageEventType.GUARDRAIL_FLAGGED) {
                    /** The request was blocked by a guardrail check */
                    hasErrored = true;
                    feed.value.push({
                        type: AgentChatMessageType.ALERT,
                        data: {
                            side: AgentChatMessageType.RESPONSE,
                            severity: AgentChatGuardrailSeverity,
                            message: event.data.data.message,
                        },
                        meta: null,
                        entryKey: getNextEntryKey(),
                        createdAt: getRfc3339String(new Date()),
                    });
                    activeGeneration.value?.abortController.abort();
                    activeGeneration.value = null;

                    acPromptEventBus.emit({ guardrailTriggered: true });
                    return;
                }

                if (activeGeneration.value === null) {
                    /** The generation was stopped before the stream fully stopped and we received something extra, ignore it  */
                    return;
                }

                if (event.eventType === AgentChatMessageEventType.CREATED) {
                    /** Usually the first message we will receive from the AI, take note of the response ID so we can re-use it later */
                    activeGeneration.value.response.data.responseId = event.data.data.responseId;
                } else if (event.eventType === AgentChatMessageEventType.PARTIAL_IMAGE) {
                    /** A partial image has been generated by the AI, show it! */
                    activeGeneration.value.response.data.image = event.data.data.image;
                } else if (event.eventType === AgentChatMessageEventType.DELTA) {
                    /** A delta of text output from the AI, append it to the current message to show the user that something is happening */
                    activeGeneration.value.response.data.message += event.data.data.delta;
                }

                /** Ignore any other message types, we don't need them (except for the final event, but apiUtils handles that) */
            },
        );

        if (activeGeneration.value === null) {
            // Already handled in the onMessage callback, or cancelled from somewhere else (i.e. resetting the feed)
            // we shouldn't add anything more to the feed
            return false;
        }

        const responseId = activeGeneration.value?.response.data.responseId ?? null;

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
                data: {
                    status: "error",
                    responseId,
                    message: response.message,
                },
                meta: null,
                entryKey: getNextEntryKey(),
                createdAt: getRfc3339String(new Date()),
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
                data: {
                    status: "error",
                    responseId: response.data.responseId,
                    message: response.data.message,
                },
                meta: null,
                entryKey: getNextEntryKey(),
                createdAt: getRfc3339String(new Date()),
            });
            return false;
        }

        /** All good at this point, persist the final response in the feed */
        feed.value.push({
            type: AgentChatMessageType.RESPONSE,
            data: {
                status: "success",
                responseId: response.data.responseId,
                image: response.data.image,
                message: response.data.message,
            },
            meta: null,
            entryKey: getNextEntryKey(),
            createdAt: getRfc3339String(new Date()),
        });

        /** Update the selected feed entry to the latest one, doesn't do anything if the implementation doesn't use this function */
        selectedFeedEntry.value = feed.value[feed.value.length - 1].entryKey;

        return true;
    }

    /**
     * Show a lightbox with all the images in the feed
     * @param entryKey - The entry key that should be selected initially
     */
    async function spawnLightbox(entryKey: AnyAgentChatFeedMessage["entryKey"]): Promise<void> {
        // only show successful responses that contain images
        const finalFeedItems = feed.value
            .filter((item) => item.type === AgentChatMessageType.RESPONSE)
            .filter((item) => item.data.status === "success");

        const slides: GalleryItem[] = [];
        let selectedIndex = 0;

        for (const item of finalFeedItems) {
            if (item.data.image) {
                slides.push({ src: `data:${item.data.image.mime};base64,${item.data.image.b64}` });

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
        getLatestFeedEntry,
        importFeed,
        activeGeneration,
        getNextEntryKey,
        getPreviousResponseId,
        sendRequest,
        selectedFeedEntry,
        spawnLightbox,
    };
});
