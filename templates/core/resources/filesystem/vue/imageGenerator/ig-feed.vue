<script lang="ts" setup>
import { useImageGeneratorStore } from "./store/imageGeneratorStore";
import IgFeedRequest from "./ig-feed-request.vue";
import IgFeedResponse from "./ig-feed-response.vue";

const igStore = useImageGeneratorStore();
const feedEl = window.Vue.useTemplateRef<HTMLDivElement>("feedRef");

/**
 * Scrolls to the end of the feed to ensure new content is shown when it is added
 */
async function scrollToEnd(): Promise<void> {
    await new Promise((resolve) => {
        setTimeout(resolve, 100); // To allow time for the new content to be stable, otherwise it leaves a little gap
    });

    if (!feedEl.value) {
        throw new Error("Unable to find feed element");
    }

    feedEl.value.scrollTo({
        top: feedEl.value.scrollHeight,
        behavior: "smooth",
    });
}
</script>

<template>
    <div
        v-if="igStore.isGenerating || igStore.generateError || igStore.feed.length > 0"
        ref="feedRef"
        class="l-mar-m--bottom l-pad-s ig-feed"
    >
        <template v-if="igStore.isGenerating">
            <ig-feed-response :message="null" @ready="scrollToEnd" />
            <br />
        </template>

            <br v-if="index > 0" />

            <ig-feed-request v-if="message.type === 'request'" :message="message" />
            <ig-feed-response v-else-if="message.type === 'response'" :message="message" @ready="scrollToEnd" />
        </template>
    </div>
</template>

<style scoped lang="scss">
.ig-feed {
    border: 1px solid #b3b3b3;
    max-height: 35vh;
    overflow-y: auto;
}
</style>
