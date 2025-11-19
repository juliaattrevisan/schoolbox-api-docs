<script lang="ts" setup>
import { useAgentChatStore } from "./store/agentChatStore";
import type { AgentChatResponse } from "./agentChatTypes";

interface Props {
    /**
     * The message to display
     */
    message: AgentChatResponse;
    /** Whether the user can select this response */
    allowSelection: boolean;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

const radioId = window.Vue.computed(() => {
    return `ac-feed-response-${props.message.entryKey}`;
});

const imageSrc = window.Vue.computed(() => {
    if ("image" in props.message && props.message.image !== null) {
        if (props.message.image === false) {
            /** an image was generated, but this is a restored AI log where it was omitted */
            return false;
        }

        return `data:${props.message.image.mime};base64,${props.message.image.b64}`;
    }
    return null;
});
</script>

<template>
    <div v-if="props.message.status === 'in_progress'" class="ac-feed-response">
        <div class="loader"></div>
        <div>
            <img v-if="imageSrc" :src="imageSrc" />

            <p
                class="l-mar--0-bottom ac-feed-message-content"
                :class="{ 'ac-feed-message-loading': !imageSrc, 'l-mar-s--top': imageSrc }"
            >
                {{ props.message.message.length === 0 ? "Generating... Please wait" : props.message.message }}
            </p>
        </div>
    </div>

    <div v-else class="ac-feed-response ac-feed-response-success radiolist">
        <template v-if="props.allowSelection">
            <input
                :id="radioId"
                v-model="acStore.selectedFeedEntry"
                type="radio"
                :name="radioId"
                :value="props.message.entryKey"
                :disabled="props.message.status !== 'success' || undefined"
            />
            <label :for="radioId"> </label>
        </template>

        <p v-if="props.message.status === 'error'" class="alert-box alert l-mar--0">
            <b>There was a problem generating the image.</b>
            {{ props.message.message }}
        </p>

        <div v-else>
            <div v-if="imageSrc" class="actions-small-1">
                <nav>
                    <a :href="imageSrc" class="icon-download" title="Download" download></a>
                </nav>
                <img :src="imageSrc" @click="() => acStore.spawnLightbox(props.message.entryKey)" />
            </div>
            <div v-else-if="imageSrc === false" class="ac-img-omitted empty-state l-mar--0">
                <i class="icon-image"></i>
            </div>
            <p class="l-mar--0-bottom ac-feed-message-content" :class="{ 'l-mar-s--top': imageSrc !== null }">
                {{ props.message.message }}
            </p>
        </div>
    </div>
</template>

<style scoped lang="scss">
.ac-feed-response {
    width: 80%;
    display: flex;

    img {
        max-width: 200px;
        max-height: 200px;
    }

    .ac-img-omitted {
        max-width: 200px;
        width: 100%;
    }

    &.ac-feed-response-success img {
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

    .ac-feed-message-loading {
        margin-top: 0.125rem;
    }

    .ac-feed-message-content {
        white-space: pre-wrap;
    }
}
</style>
