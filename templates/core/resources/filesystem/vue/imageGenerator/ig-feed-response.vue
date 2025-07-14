<script lang="ts" setup>
import { useImageGeneratorStore } from "./store/imageGeneratorStore";
import type { ImageResponse } from "./imageGeneratorTypes";

interface Props {
    /**
     * The message to display, null to show the loading state
     */
    message: ImageResponse | null;
}

const props = defineProps<Props>();
const igStore = useImageGeneratorStore();

const radioId = window.Vue.computed(() => {
    return `ig-feed-response-${props.message ? props.message.entryKey : "loading"}`;
});
</script>

<template>
    <div class="ig-feed-response radiolist">
        <input
            :id="radioId"
            v-model="igStore.selectedFeedEntry"
            type="radio"
            :name="radioId"
            :value="props.message?.entryKey ?? null"
            :disabled="props.message?.status !== 'success' || undefined"
        />
        <label :for="radioId">
            <template v-if="props.message === null">
                <div class="c-card c-card--loading" style="width: 200px; height: 120px"></div>
                <p class="l-mar--0-bottom l-mar-s--top">Generating image... Please wait</p>
            </template>
            <div v-else-if="props.message.status === 'error'" class="alert-box alert l-mar--0">
                <b>There was a problem generating the image.</b>
                {{ props.message.message }}
            </div>
            <template v-else>
                <img :src="props.message.src" />
                <p v-if="props.message.message" class="l-mar--0-bottom l-mar-s--top">{{ props.message.message }}</p>
            </template>
        </label>
    </div>
</template>

<style scoped lang="scss">
.ig-feed-response {
    width: 80%;

    img {
        max-width: 200px;
        max-height: 200px;
    }
}
</style>
