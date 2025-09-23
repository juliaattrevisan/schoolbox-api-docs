<script lang="ts" setup>
/**
 * AI Image Generator used inside the resource picker modal
 * This could easily be expanded to be used in other places when we inevitably need that
 */

import { useImageGeneratorStore } from "./store/imageGeneratorStore";
import type { ImagePromptAttribute } from "./imageGeneratorTypes";
import IgPrompt from "./ig-prompt.vue";
import igFeed from "./ig-feed.vue";
import IgActions from "./ig-actions.vue";

interface Props {
    generateRoute: string;
    /**
     * The extra attributes to show in the prompt (dropdowns)
     */
    promptAttributes: ImagePromptAttribute[];
}

const props = defineProps<Props>();
const igStore = useImageGeneratorStore();

window.Vue.onMounted(() => {
    igStore.init(props.generateRoute);
});
</script>

<template>
    <div v-if="igStore.uploadLoading" class="ab-pane">
        <h2>Uploading Image</h2>
        <div class="loader"></div>
    </div>
    <div v-else class="ab-pane">
        <h2>Generate an Image</h2>
        <p>
            A good prompt will contain information on the format, style, subject, mood, light, colours, artistic style,
            and framing. See more information on these on
            <a href="/help/sso/send?returnUrl=/homepage/5438" target="_blank">Schoolbox Help</a>
        </p>
        <ig-feed />
        <ig-prompt :prompt-attributes="props.promptAttributes" />
        <div v-if="igStore.uploadError !== null" class="alert-box alert l-mar--0">
            <b>There was a problem uploading the image.</b>
            {{ igStore.uploadError }}
        </div>
        <span class="meta"
            >Schoolbox allows interaction with AI. AI can make mistakes. Please check generated content.</span
        >
    </div>
    <ig-actions />
</template>

<style scoped lang="scss">
h2 {
    /* override _dropzone.scss [.reveal-modal.file-picker h2] */
    margin: -1rem 0 1rem 0 !important;
}
</style>
