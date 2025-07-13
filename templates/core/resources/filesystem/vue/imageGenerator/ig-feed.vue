<script lang="ts" setup>
import { useImageGeneratorStore } from "./store/imageGeneratorStore";
import IgFeedRequest from "./ig-feed-request.vue";
import IgFeedResponse from "./ig-feed-response.vue";

const igStore = useImageGeneratorStore();
</script>

<template>
    <div class="l-mar-m--bottom l-pad-s ig-feed">
        <template v-for="(message, index) in igStore.feed" :key="index">
            <br v-if="index > 0" />

            <ig-feed-request v-if="message.type === 'request'" :message="message" />
            <ig-feed-response v-else-if="message.type === 'response'" :message="message" />
        </template>
    </div>
</template>

<style scoped lang="scss">
.ig-feed {
    border: 1px solid #b3b3b3;
}
</style>
