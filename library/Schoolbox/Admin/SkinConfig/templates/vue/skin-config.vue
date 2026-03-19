<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { Routes, SkinConfigData } from "../js/types/skinConfigTypes";
import { useSkinPreview } from "./composable/useSkinPreview";

const props = defineProps<{
    routes: Routes;
    configs: SkinConfigData;
}>();

const configs = window.Vue.ref<SkinConfigData>(props.configs);
const isProcessing = window.Vue.ref(false);
const loadError = window.Vue.ref<string | null>(null);
const saveText = window.Vue.computed(() => (isProcessing.value ? "Saving..." : "Save"));
const { schedulePreviewRefresh } = useSkinPreview(configs, props.routes.preview);

/**
 * Initialise colour picker inputs once the component has rendered.
 */
async function initialiseColourPickers(): Promise<void> {
    await window.Vue.nextTick();
    document.querySelectorAll("[data-colour-picker]").forEach((element) => {
        if (!(element instanceof HTMLInputElement) || element.id === "") {
            return;
        }
        document.dispatchEvent(
            new CustomEvent("colourPickerInitialise", {
                detail: {
                    el: `#${element.id}`,
                    format: "rgb",
                },
            }),
        );
    });
}

/**
 * Persist the current Skin Config payload to the backend.
 */
async function onSaveSelected(): Promise<void> {
    if (isProcessing.value) {
        return;
    }
    const toast = window.sbProps.toastActivate("Saving...");
    try {
        isProcessing.value = true;
        loadError.value = null;
        const response = await apiRequest({
            url: props.routes.post,
            method: "POST",
            body: {
                configs: configs.value,
            },
        });
        if (!response.success) {
            throw new Error(response.message || "Failed to save");
        }
        toast.timeout({ text: "Skin config successfully updated" });
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : "There was an unexpected error";
        loadError.value = errorMessage;
        toast.timeout({ text: errorMessage, isSuccess: false });
    } finally {
        isProcessing.value = false;
    }
}

/**
 * Initialise colour pickers when the component is mounted
 */
window.Vue.onMounted(() => {
    void initialiseColourPickers();
});
</script>

<template>
    <div data-test="skin-config-container">
        <form method="post" @submit.prevent="onSaveSelected">
            <div
                v-for="(subcategories, categoryKey) in configs"
                :key="categoryKey"
                class="small-12 island"
                :data-test="`skin-config-category-${categoryKey}`"
            >
                <div class="row">
                    <div class="list-item">
                        <div class="small-12 island">
                            <h2 class="subheader">{{ categoryKey }}</h2>
                        </div>
                    </div>
                </div>
                <section>
                    <fieldset
                        v-for="subcategory in subcategories"
                        :key="subcategory.name"
                        class="content"
                        :data-test="`skin-config-subcategory-${subcategory.name}`"
                    >
                        <div v-if="subcategory.name" class="small-12 columns">
                            <h3>{{ subcategory.name }}</h3>
                        </div>
                        <div
                            v-for="option in subcategory.options"
                            :key="option.id"
                            :class="[
                                option.type === 'textarea'
                                    ? 'small-12 columns'
                                    : 'small-12 medium-6 large-3 l-mar-m--bottom columns',
                            ]"
                        >
                            <label :id="option.name" :for="`${option.name}-field`" class="inline-block">
                                <span>{{ option.title || option.name }}</span>
                                <span
                                    v-if="option.description"
                                    data-tooltip
                                    class="has-tip tip-top hint"
                                    data-allow-html="true"
                                    :title="option.description"
                                    >?</span
                                >
                            </label>
                            <div v-if="option.type === 'colour'">
                                <input
                                    :id="`${option.name}-field`"
                                    v-model="option.value"
                                    type="text"
                                    :name="option.name"
                                    data-colour-picker
                                    :disabled="!option.editable"
                                    @input="schedulePreviewRefresh"
                                    @change="schedulePreviewRefresh"
                                />
                            </div>
                            <div v-else-if="option.type === 'textarea'">
                                <textarea
                                    :id="`${option.name}-field`"
                                    v-model="option.value"
                                    :name="option.name"
                                    rows="8"
                                    :disabled="!option.editable"
                                    @input="schedulePreviewRefresh"
                                    @change="schedulePreviewRefresh"
                                />
                            </div>
                        </div>
                    </fieldset>
                </section>
            </div>
            <div class="small-12 island">
                <div class="row">
                    <div class="small-12 column">
                        <input type="submit" name="btnSubmit" :value="saveText" />
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
