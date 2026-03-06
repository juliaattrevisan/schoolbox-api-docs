<script setup lang="ts">
/**
 * A generic component to summon a CKEditor anywhere in Vue
 */

import type { PartialSbxEditorConfig } from "@js/editor/types/editorTypes";
import { useEditor } from "@schoolboxVue/composables/editor/useEditor";

interface Props {
    id: string;
    required?: boolean;
    config?: Omit<PartialSbxEditorConfig, "targetElements">;
}

const props = defineProps<Props>();
const body = defineModel<string | null>("body", { required: true });
const emit = defineEmits(["editorLoaded"]);

/** Proxy for the body model to handle null values gracefully */
const bodyProxy = window.Vue.computed({
    get: () => body.value ?? "",
    set: (val: string) => {
        body.value = val;
    },
});

const textareaEl = window.Vue.useTemplateRef<HTMLTextAreaElement>("textareaEl");

window.Vue.onMounted(async () => {
    await new Promise((resolve) => {
        if (!textareaEl.value) throw new Error("Unable to initialise <text-editor> without a textarea element");
        useEditor(
            bodyProxy,
            textareaEl.value,
            {
                targetElements: [textareaEl.value],
                ...props.config,
            },
            true,
            resolve,
            true,
        );
    });

    emit("editorLoaded");
});

window.Vue.onBeforeUnmount(async () => {
    const existingEditor = window.sbProps.editors[props.id];
    if (existingEditor) {
        await existingEditor.destroy();
    }
});
</script>

<template>
    <textarea
        :id="props.id"
        ref="textareaEl"
        v-model="bodyProxy"
        :required="props.required || undefined"
        :name="props.id"
    ></textarea>
</template>
