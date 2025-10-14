<script lang="ts" setup>
import { useTemplateRefsList } from "@vueuse/core";
import FileDrop from "@js/resource/file-drop.vue";
import type { FileDropFile } from "@js/resource/fileDropTypes";
import { useAgentChatStore } from "./store/agentChatStore";
import type { AgentChatPromptAttribute } from "./agentChatTypes";

interface Props {
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
    aiSessionId: string;
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

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
 * Send the current prompt to the AI
 */
async function sendRequest(): Promise<void> {
    if (promptEl.value) {
        promptEl.value.value = promptEl.value.value.trim();
        if (!promptEl.value.reportValidity()) {
            return; // prompt is required, can't send the request
        }
    }

    if (attributeEls.value.some((el) => !el.reportValidity())) {
        return; // some attributes are required and not set yet, can't send the request
    }

    const isSuccess = await acStore.sendRequest(
        {
            userPrompt: prompt.value.trim(),
            attributes: attributes.value,
            imageUrl: currentFile.value ? await getImageUrl(currentFile.value.file) : null,
            previousResponseId: acStore.getPreviousResponseId(),
            aiSessionId: props.aiSessionId,
        },
        currentFile.value,
    );

    if (isSuccess) {
        /* clear the prompt but not the other settings on success */
        prompt.value = "";
        currentFile.value = null;
    }
}

/**
 * The form is disabled if:
 * - there is an active generation in progress
 * - an invalid image was uploaded (the user must remove it first)
 */
const isFormDisabled = window.Vue.computed(() => {
    if (acStore.activeGeneration !== null) {
        return true;
    }

    if ((currentFile.value?.error ?? false) !== false) {
        return true;
    }

    return false;
});
</script>

<template>
    <!-- file context is only available for the first message -->
    <file-drop
        v-if="acStore.feed.length === 0"
        id="ac-prompt-file-drop"
        v-model:current-file="currentFile"
        placeholder="Click or drag here to add an image prompt"
        accept="image/jpeg,image/png,image/gif,image/webp"
    />

    <label>
        <textarea
            ref="promptRef"
            v-model.trim="prompt"
            name="prompt"
            rows="1"
            placeholder="Describe or refine the image you want to generate"
            style="resize: vertical; margin-bottom: 0"
            :disabled="isFormDisabled || undefined"
            required
            @keydown.enter.exact.prevent="sendRequest"
        ></textarea>
    </label>
    <div class="ac-prompt-actions actions-small-1 l-mar-m--bottom">
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
                title="Generate Image"
                :disabled="isFormDisabled || undefined"
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
