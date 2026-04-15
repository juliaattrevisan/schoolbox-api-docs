<script lang="ts" setup>
import { useEventBus } from "@vueuse/core";
import { getDayjsDatetimeLong } from "@js/util/dateUtils";
import { useAgentChatStore } from "./store/agentChatStore";
import { AgentChatMessageType } from "./agentChatTypes";
import type { AgentChatFeedMessage } from "./agentChatTypes";
import { acPromptEventKey } from "./agentChatSymbols";
import { formatTimestamp } from "./agentChatUtils";

interface Props {
    /** The message to display */
    message: AgentChatFeedMessage<AgentChatMessageType.RESPONSE>;
    /** A message to display before the actual error message when generation fails */
    errorPrefix?: string;
    /** Whether failed responses can be retried */
    allowRetry?: boolean;
    /** Whether the user can select this response */
    allowSelection: boolean;
    /** The icon to use for the agent's portrait in responses, default is 'ai' */
    agentIcon?: string;
    /** If timestamps should be shown for each message */
    showTimestamps?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    errorPrefix: undefined,
    agentIcon: "ai",
});
const acStore = useAgentChatStore();
const acPromptEventBus = useEventBus(acPromptEventKey);

const radioId = window.Vue.computed(() => {
    return `ac-feed-response-${props.message.entryKey}`;
});

const imageSrc = window.Vue.computed(() => {
    if ("image" in props.message.data && props.message.data.image !== null && props.message.data.image !== undefined) {
        if (props.message.data.image === false) {
            /** an image was generated, but this is a restored AI log where it was omitted */
            return false;
        }

        return `data:${props.message.data.image.mime};base64,${props.message.data.image.b64}`;
    }
    return null;
});

const isResponseComplete = window.Vue.computed(() => props.message.data.status !== "in_progress");

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
    <div
        class="ac-feed-response"
        :class="{
            'ac-feed-response-success': isResponseComplete,
            radiolist: props.allowSelection && imageSrc && isResponseComplete,
        }"
        :data-test="`ac-feed-response-list-item-${props.message.entryKey}`"
    >
        <div class="ac-feed-side l-flex-row l-mar-s--right">
            <!-- When loading -->
            <div v-if="!isResponseComplete" class="loader" data-test="ac-feed-response-loading"></div>

            <!-- When not loading, show a radio button to select this response -->
            <div v-if="isResponseComplete && props.allowSelection && imageSrc" class="ac-feed-select">
                <input
                    :id="radioId"
                    v-model="acStore.selectedFeedEntry"
                    type="radio"
                    :name="radioId"
                    :value="props.message.entryKey"
                    :disabled="props.message.data.status !== 'success' || undefined"
                />
                <label :for="radioId"> </label>
            </div>

            <!-- When not loading, show the agent's portrait -->
            <div v-if="isResponseComplete" class="ac-feed-message-portrait">
                <div class="c-img c-img--round c-img--xs">
                    <div class="c-img__wrapper">
                        <div class="icon" :class="`icon-${props.agentIcon}`"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- response errored -->
        <template v-if="props.message.data.status === 'error'">
            <p class="alert-box alert l-mar--0 l-pad-s" data-test="ac-feed-message-content">
                <b v-if="props.errorPrefix">{{ props.errorPrefix }}</b>
                {{ props.message.data.message }}
            </p>
            <div v-if="props.allowRetry && isLatestMessage" class="l-mar-xs--left">
                <button
                    class="button f-no-icon-button sbx-icon-only-button"
                    type="button"
                    title="Retry"
                    data-tooltip
                    data-test="ac-feed-response-retry-button"
                    @click="retryResponse"
                >
                    <sbx-icon name="refresh" size="m" color="currentColor"></sbx-icon>
                </button>
            </div>
        </template>

        <!-- response still loading -->
        <div v-else-if="!isResponseComplete" class="ac-feed-response-container">
            <img v-if="imageSrc" :src="imageSrc" />

            <div class="ac-feed-message-container l-pad-s" :class="{ 'l-mar-s--top': imageSrc }">
                <!-- we don't render the in progress message as HTML as it may be malformed -->
                <p class="ac-feed-message-content" data-test="ac-feed-message-content">
                    {{
                        props.message.data.message.length === 0
                            ? "Generating... Please wait"
                            : props.message.data.message
                    }}
                </p>
            </div>
        </div>

        <!-- successful response -->
        <div v-else class="ac-feed-response-container">
            <div v-if="imageSrc" class="actions-small-1">
                <nav>
                    <a :href="imageSrc" class="sbx-icon-only-button" title="Download" aria-label="Download" download
                        ><sbx-icon name="download"></sbx-icon
                    ></a>
                </nav>
                <img :src="imageSrc" @click="() => acStore.spawnLightbox(props.message.entryKey)" />
            </div>
            <div v-else-if="imageSrc === false" class="ac-img-omitted empty-state no-margin">
                <i class="icon-image no-margin"></i>
            </div>
            <div
                v-if="props.message.data.message.length"
                class="ac-feed-message-container l-pad-s"
                :class="{ 'l-mar-s--top': imageSrc !== null }"
            >
                <!-- eslint-disable vue/no-v-html -- scrubbed in the backend -->
                <p
                    class="ac-feed-message-content"
                    data-test="ac-feed-message-content"
                    v-html="props.message.data.message"
                ></p>
                <!-- eslint-enable vue/no-v-html -->
                <p v-if="props.showTimestamps" class="meta l-mar--0">
                    <time :datetime="props.message.createdAt" :title="getDayjsDatetimeLong(props.message.createdAt)">
                        {{ formatTimestamp(props.message) }}
                    </time>
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
.ac-feed-response {
    align-items: flex-start;
    display: flex;
    justify-content: flex-start;
    margin-right: 20%;
    width: 80%;

    img {
        max-width: 200px;
        max-height: 200px;
    }

    .ac-feed-response-container {
        min-width: 20%;
    }

    .ac-feed-message-container {
        background-color: #e4e4e4;
    }

    .ac-feed-select {
        align-self: start;

        label {
            padding: 0 0 0 1.5rem; // padding-right same width as the radio button
        }
    }

    .ac-feed-side {
        // allows the input/portrait to be aligned to the top/bottom correctly
        align-self: stretch;
        flex-basis: min-content;
        justify-content: center;

        // since portraits can appear, keep all entries consistent when there isn't one (like when loading)
        min-width: 40px;
    }

    .ac-feed-message-portrait {
        align-self: end;

        .c-img__wrapper {
            width: 100%;
            height: 100%;
            background-color: #e4e4e4;
        }
    }

    /**
     * we don't want margins at the end of the content but since <p> tags can be given
     * to us from the AI, we have to target them specifically
     */
    .ac-feed-message-content,
    .ac-feed-message-content :deep(p:last-child) {
        margin: 0;
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
        width: 200px;
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
        margin: 0.5rem 0;
    }
}
</style>
