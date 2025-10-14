<script lang="ts" setup>
import { useAgentChatStore } from "./store/agentChatStore";
import AcFeedRequest from "./ac-feed-request.vue";
import AcFeedResponse from "./ac-feed-response.vue";

const acStore = useAgentChatStore();

const feed = window.Vue.computed(() => {
    /** To get scrolling to happen automatically, we must use flex-direction: column-reverse and reverse the list here */
    const reversedFeed = acStore.feed.slice().reverse();

    /** Is it generating? Add the partial entry to the bottom of the feed */
    if (acStore.activeGeneration !== null) {
        return [acStore.activeGeneration, ...reversedFeed];
    }

    return reversedFeed;
});
</script>

<template>
    <div v-if="feed.length > 0" class="l-mar-m--bottom l-pad-s ac-feed">
        <template v-for="(message, index) in feed" :key="message.entryKey">
            <br v-if="index > 0" />

            <ac-feed-request v-if="message.type === 'request'" :message="message" />
            <ac-feed-response v-else :message="message" />
        </template>
    </div>
</template>

<style scoped lang="scss">
.ac-feed {
    border: 1px solid #b3b3b3;
    max-height: 35vh;
    overflow-y: auto;

    display: flex;
    flex-direction: column-reverse;
    /* To prevent the list from scrolling if you click an input */
    position: relative;
}
</style>
