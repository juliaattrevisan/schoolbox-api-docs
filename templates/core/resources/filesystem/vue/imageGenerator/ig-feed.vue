<script lang="ts" setup>
import { useImageGeneratorStore } from "./store/imageGeneratorStore";
import IgFeedRequest from "./ig-feed-request.vue";
import IgFeedResponse from "./ig-feed-response.vue";

const igStore = useImageGeneratorStore();

const feed = window.Vue.computed(() => {
    /** To get scrolling to happen automatically, we must use flex-direction: column-reverse and reverse the list here */
    const reversedFeed = igStore.feed.slice().reverse();

    /** Is it generating? Add an empty entry */
    if (igStore.isGenerating) {
        const loadingEntry = { type: "loading", entryKey: reversedFeed.length + 1 } as const;
        return [loadingEntry, ...reversedFeed];
    }

    return reversedFeed;
});
</script>

<template>
    <div v-if="feed.length > 0" class="l-mar-m--bottom l-pad-s ig-feed">
        <template v-for="(message, index) in feed" :key="message.entryKey">
            <br v-if="index > 0" />

            <ig-feed-request v-if="message.type === 'request'" :message="message" />
            <ig-feed-response v-else-if="message.type === 'response'" :message="message" />
            <ig-feed-response v-else-if="message.type === 'loading'" :message="null" />
        </template>
    </div>
</template>

<style scoped lang="scss">
.ig-feed {
    border: 1px solid #b3b3b3;
    max-height: 35vh;
    overflow-y: auto;

    display: flex;
    flex-direction: column-reverse;
}
</style>
