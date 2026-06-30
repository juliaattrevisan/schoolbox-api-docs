<script lang="ts" setup>
/**
 * AI Image Generator used inside the resource picker modal
 * This could easily be expanded to be used in other places when we inevitably need that
 */

import type { AiSessionId } from "@schoolboxLibrary/Admin/OpenAI/Usage/templates/payload";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import type { AgentChatPromptAttribute } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import AcPrompt from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-prompt.vue";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import IgActions from "./ig-actions.vue";

interface Props {
    generateRoute: string;
    /**
     * The extra attributes to show in the prompt (dropdowns)
     */
    promptAttributes: AgentChatPromptAttribute[];
    /**
     * to keep track of the current ai conversation thread
     * on each load of the tab (in the dropzone modal) this will regen from the backend
     *
     * This should be unique for a new unique conversation
     */
    aiSessionId: AiSessionId;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

// State to keep track of the final upload process
const uploadLoading = window.Vue.ref<boolean>(false);
const uploadError = window.Vue.ref<string | null>(null);
</script>

<template>
    <div v-if="uploadLoading" class="ab-pane">
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
        <ac-feed
            v-if="acStore.feed.length > 0"
            :feed="acStore.fullFeed"
            :allow-retry="!!props.generateRoute"
            class="u-mar-m--bottom"
            max-height="35vh"
            allow-selection
            error-prefix="There was a problem generating the image."
        />
        <!-- file context is only available for the first message for now -->
        <ac-prompt
            :request-config="{
                messageRoute: props.generateRoute,
                aiSessionId: props.aiSessionId,
                requireImage: true,
            }"
            prompt-placeholder="Describe or refine the image you want to generate"
            prompt-button-title="Generate Image"
            :allow-file-drop="acStore.feed.length === 0"
            :prompt-attributes="props.promptAttributes"
        />
        <div v-if="uploadError !== null" class="alert-box alert u-mar--0">
            <b>There was a problem uploading the image.</b>
            {{ uploadError }}
        </div>
        <span class="meta">AI sometimes gets things wrong — please check important details.</span>
    </div>
    <ig-actions v-model:upload-loading="uploadLoading" v-model:upload-error="uploadError" />
</template>

<style scoped lang="scss">
h2 {
    /* override _dropzone.scss [.reveal-modal.file-picker h2] */
    margin: -1rem 0 1rem 0 !important;
}
</style>
