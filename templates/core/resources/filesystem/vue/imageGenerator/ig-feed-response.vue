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
        <label :for="radioId"> </label>

        <div v-if="props.message === null">
            <div style="width: 200px">
                <div class="loader"></div>
            </div>
            <p class="l-mar--0-bottom l-mar-s--top">Generating image... Please wait</p>
        </div>

        <div v-else-if="props.message.status === 'error'" class="alert-box alert l-mar--0">
            <b>There was a problem generating the image.</b>
            {{ props.message.message }}
        </div>

        <div v-else>
            <div class="actions-small-1">
                <nav>
                    <a :href="props.message.src" class="icon-download" title="Download" download></a>
                </nav>
                <img :src="props.message.src" />
            </div>
            <p v-if="props.message.message" class="l-mar--0-bottom l-mar-s--top ig-feed-message-content">
                {{ props.message.message }}
            </p>
        </div>
    </div>
</template>

<style scoped lang="scss">
.ig-feed-response {
    width: 80%;
    display: flex;

    img {
        max-width: 200px;
        max-height: 200px;
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

    .ig-feed-message-content {
        white-space: pre-wrap;
    }
}
</style>
