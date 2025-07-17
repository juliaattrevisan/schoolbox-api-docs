<script lang="ts" setup>
import { useTemplateRefsList } from "@vueuse/core";
import { useImageGeneratorStore } from "./store/imageGeneratorStore";
import type { ImagePromptAttribute } from "./imageGeneratorTypes";

interface Props {
    /**
     * The extra attributes to show in the prompt (dropdowns)
     */
    promptAttributes: ImagePromptAttribute[];
}

const props = defineProps<Props>();
const igStore = useImageGeneratorStore();

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
 * Temp function to test the prompt
 */
async function sendRequest(): Promise<void> {
    if (!promptEl.value?.reportValidity()) {
        return; // prompt is required, can't send the request
    }

    if (attributeEls.value.some((el) => !el.reportValidity())) {
        return; // some attributes are required and not set yet, can't send the request
    }

    const isSuccess = await igStore.sendRequest({
        userPrompt: prompt.value,
        attributes: attributes.value,
        previousResponseId: igStore.getPreviousResponseId(),
    });

    if (isSuccess) {
        /* clear the prompt but not the other settings on success */
        prompt.value = "";
    }
}
</script>

<template>
    <label>
        <textarea
            ref="promptRef"
            v-model="prompt"
            name="prompt"
            rows="1"
            placeholder="Describe or refine the image you want to generate"
            style="resize: vertical; margin-bottom: 0"
            :disabled="igStore.isGenerating || undefined"
            required
            @keydown.enter.exact.prevent="sendRequest"
        ></textarea>
    </label>
    <div class="ig-prompt-actions actions-small-1 l-mar-m--bottom">
        <div class="list-item">
            <select
                v-for="(attr, index) in props.promptAttributes"
                :key="index"
                :ref="attributeEls.set"
                v-model="attributes[attr.id]"
                :name="attr.id"
                :disabled="igStore.isGenerating || undefined"
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
                :disabled="igStore.isGenerating || undefined"
                @click.prevent="sendRequest"
            ></a>
        </nav>
    </div>
</template>

<style scoped lang="scss">
.ig-prompt-actions {
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
