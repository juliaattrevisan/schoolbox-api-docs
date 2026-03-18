<script lang="ts" setup>
import { useEventBus } from "@vueuse/core";
import AcFeedAlert from "./ac-feed-alert.vue";
import AcFeedRequest from "./ac-feed-request.vue";
import AcFeedResponse from "./ac-feed-response.vue";
import { AgentChatMessageType } from "./agentChatTypes";
import type { AnyAgentChatFeedMessage } from "./agentChatTypes";
import { acPromptEventKey } from "./agentChatSymbols";

interface Props {
    feed: AnyAgentChatFeedMessage[];
    /** A message to display before the actual error message when generation fails */
    errorPrefix?: string;
    /** Whether failed responses can be retried */
    allowRetry?: boolean;
    /** Whether the user can select this response */
    allowSelection?: boolean;
    /** The maximum height of the feed container, if any */
    maxHeight?: string;
    /** The minimum height of the feed container, if any */
    minHeight?: string;
    /** Should the feed be initially scrolled to the bottom? */
    initiallyScrolled?: boolean;
    /** The icon to use for the agent's portrait in responses */
    agentIcon?: string;
    /** If timestamps should be shown for each message */
    showTimestamps?: boolean;
}
const props = defineProps<Props>();
const acPromptEventBus = useEventBus(acPromptEventKey);

const feedEl = window.Vue.useTemplateRef<HTMLDivElement>("feedRef");

/**
 * Scroll the anchor into view so that the feed is scrolled and fixed to the bottom
 * @param behavior - How the scroll should behave
 */
function scrollFeedToBottom(behavior: ScrollBehavior): void {
    const feed = feedEl.value;
    if (feed) {
        setTimeout(() => feed.scrollTo({ top: feed.scrollHeight, behavior }), 100);
    }
}

window.Vue.onMounted(() => {
    if (props.initiallyScrolled) {
        scrollFeedToBottom("instant");
    }
});

acPromptEventBus.on(({ scrollFeed }) => {
    if (scrollFeed) {
        scrollFeedToBottom("smooth");
    }
});

defineExpose({ feedEl });
</script>

<template>
    <div class="ac-feed" data-test="ac-feed">
        <slot name="header"></slot>

        <div
            ref="feedRef"
            class="l-pad-s ac-feed-list-wrapper"
            :style="{
                maxHeight: props.maxHeight,
                minHeight: props.minHeight,
                height: props.maxHeight ? '100%' : undefined,
            }"
        >
            <div class="ac-feed-list">
                <template v-for="(message, index) in props.feed" :key="message.entryKey">
                    <br v-if="index > 0" />

                    <ac-feed-alert v-if="message.type === AgentChatMessageType.ALERT" :message="message" />
                    <ac-feed-request
                        v-else-if="message.type === AgentChatMessageType.REQUEST"
                        :message="message"
                        :show-timestamps="props.showTimestamps"
                    />
                    <ac-feed-response
                        v-else
                        :message="message"
                        :error-prefix="props.errorPrefix"
                        :allow-retry="props.allowRetry"
                        :allow-selection="props.allowSelection"
                        :agent-icon="props.agentIcon"
                        :show-timestamps="props.showTimestamps"
                    />
                </template>

                <div class="ac-feed-anchor"></div>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
.ac-feed {
    border: 1px solid #b3b3b3;
}

.ac-feed-list-wrapper {
    overflow-y: auto;
}

.ac-feed-list {
    display: flex;
    flex-direction: column;
    justify-content: flex-end;

    /* To prevent the list from scrolling if you click an input */
    position: relative;
}

/**
 * browsers try to avoid the page from shifting when new content is added,
 * This makes it so that as long as the anchor is visible on the page the browser
 * is forced to keep it in view, effectively making it so that the container is pinned to the bottom
 */
.ac-feed-list * {
    overflow-anchor: none;
}

.ac-feed-anchor {
    overflow-anchor: auto;
    height: 1px;
}

@media print {
    .ac-feed {
        border: none;
    }

    .ac-feed-list-wrapper {
        max-height: 100% !important;
        height: auto !important;
        overflow: visible !important;
    }
}
</style>
