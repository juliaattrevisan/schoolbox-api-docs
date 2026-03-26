<script setup lang="ts">
/**
 * Vue component for rendering and managing the Skin Config form in the Schoolbox Admin interface.
 * This component handles displaying the configuration options, tracking changes, and saving updates to the backend.
 */

import { apiRequest } from "@js/util/sbx/apiUtils";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import type { Routes, SkinConfigData } from "../js/types/skinConfigTypes";
import ScCategories from "./sc-categories.vue";
import { useSkinConfigState } from "./composable/useSkinConfigState";
import { useSkinPreview } from "./composable/useSkinPreview";

const props = defineProps<{
    routes: Routes;
    configs: SkinConfigData;
}>();

const isProcessing = window.Vue.ref(false);
const loadError = window.Vue.ref<string | null>(null);
const saveText = window.Vue.computed(() => (isProcessing.value ? "Saving..." : "Save"));
const { configs, hasUnsavedChanges, initialiseColourPickers, resetToSavedState, markCurrentAsSaved } =
    useSkinConfigState(props.configs, isProcessing, () => {
        schedulePreviewRefresh();
    });
const { schedulePreviewRefresh } = useSkinPreview(configs, props.routes.preview);

window.Vue.watch(
    () => configs.value.toggle.enabled,
    async () => {
        schedulePreviewRefresh();
        await initialiseColourPickers();
    },
);

/**
 * Restore the last saved config state.
 * @returns A promise that resolves once the form state and preview have been reset.
 */
async function onUndoChanges(): Promise<void> {
    loadError.value = null;
    await resetToSavedState();
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
        await markCurrentAsSaved();
        toast.timeout({ text: "Skin config successfully updated" });
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : "There was an unexpected error";
        loadError.value = errorMessage;
        toast.timeout({ text: errorMessage, isSuccess: false });
    } finally {
        isProcessing.value = false;
    }
}
</script>

<template>
    <div data-test="skin-config-container" class="l-pad-m--horiz">
        <div v-if="loadError" class="small-12 columns" data-test="skin-config-load-error">
            <request-error :further-info="loadError" />
        </div>
        <form method="post" class="l-flex-col l-gap-l skin-config-form" @submit.prevent="onSaveSelected">
            <div class="c-island l-pad-m">
                <label id="skin-config-toggle" class="inline-block" for="skin-config-toggle-field">
                    <span>{{ configs.toggle.title }}</span>
                    <span
                        v-if="configs.toggle.description"
                        data-tooltip
                        class="hint has-tip tip-bottom"
                        :title="configs.toggle.description"
                        >?</span
                    >
                </label>
                <div class="switch f-no-margins">
                    <input
                        id="skin-config-toggle-field"
                        v-model="configs.toggle.enabled"
                        type="checkbox"
                        name="advanced_ui_enabled"
                        autocomplete="off"
                        data-test="skin-config-toggle-input"
                    />
                    <label for="skin-config-toggle-field" data-test="skin-config-toggle">
                        <span>On</span>
                        <span>Off</span>
                    </label>
                </div>
            </div>
            <sc-categories :configs="configs" @preview-refresh="schedulePreviewRefresh" />
            <div class="l-flex l-flex--justify l-gap-xs">
                <button v-if="hasUnsavedChanges" type="button" class="button l-flex__item--1" @click="onUndoChanges">
                    Undo Changes
                </button>
                <button :value="saveText" class="button submit l-flex__item--1" data-test="skin-config-save-submit">
                    Save
                </button>
            </div>
        </form>
    </div>
</template>

<style scoped></style>
