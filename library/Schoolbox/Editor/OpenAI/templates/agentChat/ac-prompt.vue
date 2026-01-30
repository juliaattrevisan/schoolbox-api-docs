<script lang="ts" setup>
import { useEventBus, useTemplateRefsList } from "@vueuse/core";
import FileDrop from "@js/resource/file-drop.vue";
import type { FileDropFile } from "@js/resource/fileDropTypes";
import { useAgentChatStore } from "./store/agentChatStore";
import type { AgentChatRequestConfig } from "./store/agentChatStore";
import { AGENT_CHAT_MAX_PROMPT_CHARS } from "./agentChatTypes";
import type { AgentChatPromptAttribute } from "./agentChatTypes";
import { acPromptEventKey } from "./agentChatSymbols";

interface Props {
    disabled?: boolean;
    requestConfig: AgentChatRequestConfig;
    /** Whether or not to allow a file to be uploaded for image context */
    allowFileDrop?: boolean;
    /** The placeholder text for the prompt textarea */
    promptPlaceholder?: string;
    /** The title attribute for the prompt submit button */
    promptButtonTitle?: string;
    /** The extra attributes to show in the prompt (dropdowns) */
    promptAttributes: AgentChatPromptAttribute[];
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();
const acPromptEventBus = useEventBus(acPromptEventKey);

const currentFile = window.Vue.ref<FileDropFile | null>(null);
const prompt = window.Vue.ref<string>("");
const promptEl = window.Vue.useTemplateRef<HTMLTextAreaElement>("promptRef");
const attributes = window.Vue.ref<Record<string, string>>({});
const attributeEls = useTemplateRefsList<HTMLSelectElement>();

window.Vue.onMounted(() => {
    props.promptAttributes.forEach((attr) => {
        attributes.value[attr.id] = ""; // defaults
    });
});

/**
 * @param file - The relevant file
 * @returns A URL (base64) for the current file to be included in the prompt
 */
async function getImageUrl(file: File): Promise<string | null> {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onloadend = (): void => {
            if (typeof reader.result === "string" || reader.result === null) {
                resolve(reader.result);
            } else {
                resolve(null);
            }
        };
        reader.readAsDataURL(file);
    });
}

/**
 * The form is disabled if:
 * - the entire component is disabled
 * - an invalid image was uploaded (the user must remove it first)
 */
const isFormDisabled = window.Vue.computed(() => {
    if (props.disabled) {
        return true;
    }

    if ((currentFile.value?.error ?? false) !== false) {
        return true;
    }

    return false;
});

const isGenerating = window.Vue.ref<boolean>(false);

/**
 * The submit button is disabled if:
 * - the form is disabled
 * - there is an active generation in progress
 */
const isSubmitDisabled = window.Vue.computed(
    () => isFormDisabled.value || acStore.activeGeneration !== null || isGenerating.value,
);

/**
 * Send the current prompt to the AI
 */
async function sendRequest(): Promise<void> {
    if (isSubmitDisabled.value) {
        return; // form is disabled, can't send
    }

    if (promptEl.value) {
        promptEl.value.value = promptEl.value.value.trim();
        if (!promptEl.value.reportValidity()) {
            return; // prompt is required, can't send the request
        }
    }

    if (attributeEls.value.some((el) => !el.reportValidity())) {
        return; // some attributes are required and not set yet, can't send the request
    }

    /** somehow steve was sending two requests at once, I can't do it but in theory this should stop it? */
    await window.Vue.nextTick();
    if (isGenerating.value) {
        return;
    }
    isGenerating.value = true;

    const userPrompt = prompt.value.trim();
    const currentFileValue = currentFile.value;
    const imageUrl = currentFileValue !== null ? await getImageUrl(currentFileValue.file) : null;

    /* clear the prompt but not the other settings. request can be retried if it fails from ac-feed-response */
    prompt.value = "";
    currentFile.value = null;

    /**
     * Trigger the feed to be scrolled down to show the new message
     * This is only done on the user's action, not when the AI responds
     * as to not interrupt the user if they scrolled back up to read something else
     */
    acPromptEventBus.emit({ scrollFeed: true });

    await acStore.sendRequest(
        props.requestConfig,
        {
            userPrompt,
            attributes: attributes.value,
            imageUrl,
            previousResponseId: acStore.getPreviousResponseId(),
            aiSessionId: props.requestConfig.aiSessionId,
        },
        currentFileValue,
    );

    isGenerating.value = false;
}

acPromptEventBus.on(({ retryRequest }) => {
    if (retryRequest) {
        /**
         * Trigger the feed to be scrolled down to show the new message
         * This is only done on the user's action, not when the AI responds
         * as to not interrupt the user if they scrolled back up to read something else
         */
        acPromptEventBus.emit({ scrollFeed: true });

        void acStore.sendRequest(
            props.requestConfig,
            {
                ...retryRequest.meta.payload,
                previousResponseId: acStore.getPreviousResponseId(),
            },
            retryRequest.meta.file,
        );
    }
});
</script>

<template>
    <file-drop
        v-if="props.allowFileDrop"
        id="ac-prompt-file-drop"
        v-model:current-file="currentFile"
        placeholder="Click or drag here to add an image prompt"
        accept="image/jpeg,image/png,image/gif,image/webp"
        :disabled="props.disabled"
    />

    <label>
        <textarea
            ref="promptRef"
            v-model.trim="prompt"
            name="prompt"
            rows="1"
            :placeholder="props.promptPlaceholder"
            style="resize: vertical; margin-bottom: 0"
            :disabled="isFormDisabled || undefined"
            :maxlength="AGENT_CHAT_MAX_PROMPT_CHARS"
            required
            @keydown.enter.exact.prevent="sendRequest"
        ></textarea>
    </label>
    <div class="ac-prompt-actions actions-small-1 l-mar-s--bottom">
        <div class="list-item">
            <select
                v-for="(attr, index) in props.promptAttributes"
                :key="index"
                :ref="attributeEls.set"
                v-model="attributes[attr.id]"
                :name="attr.id"
                :disabled="isFormDisabled || undefined"
                :required="attr.required || undefined"
            >
                <option
                    value=""
                    style="font-style: italic"
                    :disabled="attr.required || undefined"
                    :selected="attributes[attr.id] === null"
                >
                    {{ attr.title }}
                </option>
                <option v-for="(label, value) in attr.options" :key="value" :value="value">
                    {{ label }}
                </option>
            </select>
        </div>
        <nav>
            <a
                class="icon-send"
                href="#"
                :title="props.promptButtonTitle"
                :disabled="isSubmitDisabled || undefined"
                @click.prevent="sendRequest"
            ></a>
        </nav>
    </div>
</template>

<style scoped lang="scss">
.ac-prompt-actions {
    /* mimic the style if this were an input */
    border-style: solid;
    border-width: 0.0625rem;
    border-color: var(--form-border-color);
    border-top: 0;

    .list-item {
        display: flex;
        flex-wrap: wrap;

        /* unique small selects in this area */
        select {
            height: 1.5rem;
            min-height: 1.5rem;
            margin: 0.5rem;
            max-width: 160px;
        }
    }

    /* a custom icon that isn't from the sbx font for now hence these are some rather specific margins
     * to align the icon, we should probably just add it to our actual icons... */
    .icon-send {
        height: 2.5rem;

        &::before {
            content: "➤";
            font-size: 1.5rem;
            line-height: 2.25rem;
        }
    }
}
</style>
