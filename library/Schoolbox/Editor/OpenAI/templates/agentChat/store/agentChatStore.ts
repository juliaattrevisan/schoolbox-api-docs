import { defineStore } from "pinia";
import type { GalleryItem } from "lightgallery/lg-utils";
import { IMAGE_LIGHTBOX_CREATE } from "@js/image-lightbox/imageLightboxEvent";
import type { ImageLightboxCreateEvent } from "@js/image-lightbox/imageLightboxEvent";
import type { FileDropFile } from "@js/resource/fileDropTypes";
import type { AgentChatFeedMessage, AgentChatResponseInProgress } from "../agentChatTypes";
import { AgentChatMessageEventType, generateImage } from "./agentChatPayload";
import type { AgentChatApiPayload } from "./agentChatPayload";

export const useAgentChatStore = defineStore("agentChat", () => {
    const feed = window.Vue.ref<AgentChatFeedMessage[]>([]);
    const generateRoute = window.Vue.ref<string | null>(null);

    const activeGeneration = window.Vue.ref<AgentChatResponseInProgress | null>(null);

    /** When allowing selection of a particular entry (such as in the image generator), the selected entry */
    const selectedFeedEntry = window.Vue.ref<AgentChatFeedMessage["entryKey"] | null>(null);

    // State to keep track of the final upload process
    const uploadLoading = window.Vue.ref(false);
    const uploadError = window.Vue.ref<string | null>(null);

    /**
     * Initialise the store
     * @param $generateRoute - The route to use for generating images
     */
    function init($generateRoute: string): void {
        generateRoute.value = $generateRoute;
    }

    /**
     * @returns The full feed of messages (includes the partial entry at the end if currently generating)
     */
    const fullFeed = window.Vue.computed((): AgentChatFeedMessage[] => {
        if (activeGeneration.value === null) {
            return feed.value;
        }

        return [...feed.value, activeGeneration.value];
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
     * Request a new image from the AI
     * @param payload - The payload to send to the AI
     * @param file - The file that was sent with the request (if any)
     * @returns whether the request was sent successfully or not
     */
    async function sendRequest(payload: AgentChatApiPayload, file: FileDropFile | null): Promise<boolean> {
        if (activeGeneration.value !== null) {
            return false;
        }

        if (generateRoute.value === null) {
            throw new Error("generateRoute is not set");
        }

        feed.value.push({
            type: "request",
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
            type: "response",
            status: "in_progress",
            entryKey: `loading-${getNextEntryKey()}`,
            responseId: null,
            image: null,
            message: "",
        };

        let hasErrored = false;
        const abortController = new AbortController();
        const response = await generateImage(generateRoute.value, payload, abortController.signal, (event) => {
            if (event.data.success === false) {
                /** Any arbitrary error has occured, could be a network error, server error on our side or the AI side, or something else */
                hasErrored = true;
                feed.value.push({
                    type: "response",
                    status: "error",
                    entryKey: getNextEntryKey(),
                    responseId: activeGeneration.value?.responseId ?? null,
                    message: event.data.message,
                });
                activeGeneration.value = null;
                abortController.abort();
                return;
            }

            if (activeGeneration.value === null) {
                /** The generation was stopped before the stream fully stopped and we recieved something extra, ignore it  */
                abortController.abort();
                return;
            }

            if (event.eventType === AgentChatMessageEventType.CREATED) {
                /** Usually the first message we will receive from the AI, take note of the response ID so we can re-use it later */
                activeGeneration.value.responseId = event.data.data.responseId;
            } else if (event.eventType === AgentChatMessageEventType.PARTIAL_IMAGE) {
                /** A partial image has been generated by the AI, show it! */
                activeGeneration.value.image = event.data.data.image;
            } else if (event.eventType === AgentChatMessageEventType.DELTA) {
                /** A delta of text output from the AI, append it to the current message to show the user that something is happening */
                activeGeneration.value.message += event.data.data.delta;
            }

            /** Ignore any other message types, we don't need them (except for the final event, but apiUtils handles that) */
        });

        const responseId = activeGeneration.value?.responseId ?? null;
        activeGeneration.value = null;
        abortController.abort();

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
                type: "response",
                status: "error",
                entryKey: getNextEntryKey(),
                responseId,
                message: response.message,
            });
            return false;
        }

        if (response.data.image === null) {
            /** TODO: only the image generator requires this, other types won't - make it flexible whilst still having this check for there */
            feed.value.push({
                type: "response",
                status: "error",
                entryKey: getNextEntryKey(),
                responseId: response.data.responseId,
                message: response.data.message,
            });
            return false;
        }

        /** All good at this point, persist the final response in the feed */
        feed.value.push({
            type: "response",
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
    function spawnLightbox(entryKey: AgentChatFeedMessage["entryKey"]): void {
        const slides = feed.value
            .map((item) => {
                // only show final images
                if (item.type !== "response" || item.status !== "success" || item.image === null) {
                    return null;
                }

                return {
                    key: item.entryKey,
                    src: `data:${item.image.mime};base64,${item.image.b64}`,
                };
            })
            .filter((item) => item !== null);

        document.dispatchEvent(
            new CustomEvent<ImageLightboxCreateEvent["detail"]>(IMAGE_LIGHTBOX_CREATE, {
                detail: {
                    targetKey: `ac-feed-lightbox`,
                    options: {
                        dynamic: true,
                        dynamicEl: slides.map((item): GalleryItem => ({ src: item.src })),
                        index: slides.findIndex((slide) => slide.key === entryKey),
                    },
                    onCreated: (gallery) => gallery.openGallery(),
                },
            }),
        );
    }

    return {
        init,
        feed,
        fullFeed,
        activeGeneration,
        getNextEntryKey,
        getPreviousResponseId,
        sendRequest,
        selectedFeedEntry,
        uploadLoading,
        uploadError,
        spawnLightbox,
    };
});
