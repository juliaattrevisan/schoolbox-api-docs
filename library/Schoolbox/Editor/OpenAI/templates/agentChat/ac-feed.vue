<script lang="ts" setup>
import AcFeedAlert from "./ac-feed-alert.vue";
import AcFeedRequest from "./ac-feed-request.vue";
import AcFeedResponse from "./ac-feed-response.vue";
import type { AgentChatFeedMessage } from "./agentChatTypes";

interface Props {
    feed: AgentChatFeedMessage[];
    allowSelection?: boolean;
}
const props = defineProps<Props>();

/**
 * To get scrolling to happen automatically,
 * we must use flex-direction: column-reverse and reverse the list here
 */
const reversedFeed = window.Vue.computed(() => props.feed.slice().reverse());
</script>

<template>
    <div class="ac-feed">
        <slot name="header"></slot>

        <div class="l-pad-s ac-feed-list">
            <template v-for="(message, index) in reversedFeed" :key="message.entryKey">
                <br v-if="index > 0" />

                <ac-feed-alert v-if="message.type === 'alert'" :message="message" />
                <ac-feed-request v-else-if="message.type === 'request'" :message="message" />
                <ac-feed-response v-else :message="message" :allow-selection="props.allowSelection" />
            </template>
        </div>
    </div>
</template>

<style scoped lang="scss">
.ac-feed {
    border: 1px solid #b3b3b3;
}

.ac-feed-list {
    display: flex;
    flex-direction: column-reverse;
    justify-content: flex-end;
    overflow-y: auto;

    /* To prevent the list from scrolling if you click an input */
    position: relative;
}
</style>
