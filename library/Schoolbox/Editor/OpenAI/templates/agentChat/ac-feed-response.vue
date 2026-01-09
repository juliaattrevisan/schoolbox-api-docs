<script lang="ts" setup>
import { useEventBus } from "@vueuse/core";
import { useAgentChatStore } from "./store/agentChatStore";
import { AgentChatMessageType } from "./agentChatTypes";
import type { AgentChatResponse } from "./agentChatTypes";
import { acPromptEventKey } from "./agentChatSymbols";

interface Props {
    /** The message to display */
    message: AgentChatResponse;
    /** A message to display before the actual error message when generation fails */
    errorPrefix?: string;
    /** Whether failed responses can be retried */
    allowRetry?: boolean;
    /** Whether the user can select this response */
    allowSelection: boolean;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();
const acPromptEventBus = useEventBus(acPromptEventKey);

const radioId = window.Vue.computed(() => {
    return `ac-feed-response-${props.message.entryKey}`;
});

const imageSrc = window.Vue.computed(() => {
    if ("image" in props.message && props.message.image !== null) {
        if (props.message.image === false) {
            /** an image was generated, but this is a restored AI log where it was omitted */
            return false;
        }

        return `data:${props.message.image.mime};base64,${props.message.image.b64}`;
    }
    return null;
});

/** Whether this is the most recent message of any type */
const isLatestMessage = window.Vue.computed(() => {
    const latestResponse = acStore.getLatestFeedEntry(null);
    return latestResponse && latestResponse.entryKey === props.message.entryKey;
});

/**
 * Retry using the latest request prompt
 */
function retryResponse(): void {
    const latestRequest = acStore.getLatestFeedEntry(AgentChatMessageType.REQUEST);
    if (!latestRequest) {
        console.warn("Unable to retry response: no previous request found"); // shouldn't get here
        return;
    }

    acPromptEventBus.emit({ retryRequest: latestRequest });
}
</script>

<template>
    <div v-if="props.message.status === 'in_progress'" class="ac-feed-response">
        <div class="loader"></div>
        <div>
            <img v-if="imageSrc" :src="imageSrc" />

            <div
                class="ac-feed-message-container l-pad-s"
                :class="{ 'ac-feed-message-loading': !imageSrc, 'l-mar-s--top': imageSrc }"
            >
                <p class="l-mar--0 ac-feed-message-content">
                    {{ props.message.message.length === 0 ? "Generating... Please wait" : props.message.message }}
                </p>
            </div>
        </div>
    </div>

    <div v-else class="ac-feed-response ac-feed-response-success" :class="{ radiolist: props.allowSelection }">
        <template v-if="props.allowSelection">
            <input
                :id="radioId"
                v-model="acStore.selectedFeedEntry"
                type="radio"
                :name="radioId"
                :value="props.message.entryKey"
                :disabled="props.message.status !== 'success' || undefined"
            />
            <label :for="radioId"> </label>
        </template>

        <template v-if="props.message.status === 'error'">
            <p class="alert-box alert l-mar--0 l-pad-s">
                <b v-if="props.errorPrefix">{{ props.errorPrefix }}</b>
                {{ props.message.message }}
            </p>
            <div v-if="props.allowRetry && isLatestMessage" class="l-mar-xs--left">
                <button class="button f-no-icon-button" type="button" title="Retry" data-tooltip @click="retryResponse">
                    <sbx-icon name="refresh" size="m" color="currentColor"></sbx-icon>
                </button>
            </div>
        </template>

        <div v-else>
            <div v-if="imageSrc" class="actions-small-1">
                <nav>
                    <a :href="imageSrc" class="icon-download" title="Download" download></a>
                </nav>
                <img :src="imageSrc" @click="() => acStore.spawnLightbox(props.message.entryKey)" />
            </div>
            <div v-else-if="imageSrc === false" class="ac-img-omitted empty-state l-mar--0">
                <i class="icon-image"></i>
            </div>
            <div
                v-if="props.message.message.length"
                class="ac-feed-message-container l-pad-s"
                :class="{ 'l-mar-s--top': imageSrc }"
            >
                <p class="l-mar--0 ac-feed-message-content">
                    <!-- eslint-disable-next-line vue/no-v-html -- scrubbed in the backend -->
                    <span v-html="props.message.message"></span>
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
.ac-feed-response {
    width: 80%;
    display: flex;

    img {
        max-width: 200px;
        max-height: 200px;
    }

    .ac-feed-message-container {
        background-color: #e4e4e4;
        border: 1px solid #b3b3b3;
        // flex-grow: 1;
    }

    /**
     * It's actually possible for the response to contain embedded images
     * if the AI felt like supplying a link to an image in markdown.
     *
     * When that occurs, the image could be very large, so we limit the size here.
     * Since this image won't be previewable, it can be a bit larger than the normal ones above
     */
    .ac-feed-message-content :deep(img) {
        max-width: 25rem;
        max-height: 25rem;
    }

    .ac-img-omitted {
        max-width: 200px;
        width: 100%;
    }

    &.ac-feed-response-success img {
        cursor: zoom-in;
    }

    .actions-small-1 {
        width: fit-content;

        .icon-download {
            opacity: 0;
        }

        &:hover .icon-download {
            opacity: 1;
            color: white;
            background-color: rgba(0, 0, 0, 0.6);
        }
    }

    .loader {
        min-width: 1.5rem;
        min-height: 1.5rem;
        width: 1.5rem;
        height: 1.5rem;
        margin: 0.5rem 0.5rem 0 0;
    }

    .ac-feed-message-loading {
        margin-top: 0.125rem;
    }
}
</style>
