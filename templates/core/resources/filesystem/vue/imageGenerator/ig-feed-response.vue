<script lang="ts" setup>
import { useImageGeneratorStore } from "./store/imageGeneratorStore";
import type { ImageResponse } from "./imageGeneratorTypes";

interface Props {
    /**
     * The message to display
     */
    message: ImageResponse;
}

const props = defineProps<Props>();
const igStore = useImageGeneratorStore();

const radioId = window.Vue.computed(() => {
    return `ig-feed-response-${props.message.entryKey}`;
});

const imageSrc = window.Vue.computed(() => {
    if ("image" in props.message && props.message.image) {
        return `data:${props.message.image.mime};base64,${props.message.image.b64}`;
    }
    return null;
});
</script>

<template>
    <div v-if="props.message.status === 'in_progress'" class="ig-feed-response">
        <div class="loader"></div>
        <div>
            <img v-if="imageSrc" :src="imageSrc" />

            <p
                class="l-mar--0-bottom ig-feed-message-content"
                :class="{ 'ig-feed-message-loading': !imageSrc, 'l-mar-s--top': imageSrc }"
            >
                {{ props.message.message.length === 0 ? "Generating... Please wait" : props.message.message }}
            </p>
        </div>
    </div>

    <div v-else class="ig-feed-response ig-feed-response-success radiolist">
        <input
            :id="radioId"
            v-model="igStore.selectedFeedEntry"
            type="radio"
            :name="radioId"
            :value="props.message.entryKey"
            :disabled="props.message.status !== 'success' || undefined"
        />
        <label :for="radioId"> </label>

        <p v-if="props.message.status === 'error'" class="alert-box alert l-mar--0">
            <b>There was a problem generating the image.</b>
            {{ props.message.message }}
        </p>

        <div v-else>
            <div v-if="imageSrc" class="actions-small-1">
                <nav>
                    <a :href="imageSrc" class="icon-download" title="Download" download></a>
                </nav>
                <img :src="imageSrc" @click="() => igStore.spawnLightbox(props.message.entryKey)" />
            </div>
            <p class="l-mar--0-bottom l-mar-s--top ig-feed-message-content">
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

    &.ig-feed-response-success img {
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
        margin: 0 0.5rem 0 0;
    }

    .ig-feed-message-loading {
        margin-top: 0.125rem;
    }

    .ig-feed-message-content {
        white-space: pre-wrap;
    }
}
</style>
