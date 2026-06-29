<script setup lang="ts">
/**
 * Vue component for rendering and managing the Skin Config form in the Schoolbox Admin interface.
 * This component handles displaying the configuration options, tracking changes, and saving updates to the backend.
 */

import tinycolor from "tinycolor2";
import { apiRequest } from "@js/util/sbx/apiUtils";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import type { Routes, SkinConfigData } from "../js/types/skinConfigTypes";
import ScCategories from "./sc-categories.vue";
import { useSkinConfigContrastValidation } from "./composable/useSkinConfigContrastValidation";
import { useSkinConfigState } from "./composable/useSkinConfigState";
import { useSkinPreview } from "./composable/useSkinPreview";

const sonarImgSrc = `${window.cdnPrefix}/images/tour/26.1/26_1_SonarUI_AdminTile.png`;

const props = defineProps<{
    routes: Routes;
    configs: SkinConfigData;
}>();

/**
 * Run through the SkinConfigData.sonar.Colours array and ensure
 * any colours that are hasAlpha: false, are not stored with an alpha value.
 * @param value The current Skin Config payload.
 * @returns A deep-cloned, modified Skin Config payload.
 */
function checkSavedConfigs(value: SkinConfigData): SkinConfigData {
    const clonedConfigs = JSON.parse(JSON.stringify(value)) as SkinConfigData;
    Object.values(clonedConfigs.sonar.Colours).forEach((subcategory) => {
        subcategory.options.forEach((option) => {
            if (option.hasAlpha === false && typeof option.value === "string") {
                const color = tinycolor(option.value);
                if (color.getAlpha() < 1) {
                    option.value = color.setAlpha(1).toRgbString();
                    hasInvalidColours.value = true;
                }
            }
        });
    });
    return clonedConfigs;
}

const hasInvalidColours = window.Vue.ref(false); // to trigger a save on mount if there are invalid colours that need correcting
const checkedConfigs = checkSavedConfigs(props.configs);
const isProcessing = window.Vue.ref(false);
const loadError = window.Vue.ref<string | null>(null);
const saveText = window.Vue.computed(() => (isProcessing.value ? "Saving..." : "Save"));
const {
    configs: editableConfigs,
    hasUnsavedChanges,
    initialiseColourPickers,
    resetToSavedState,
    markCurrentAsSaved,
} = useSkinConfigState(checkedConfigs, isProcessing, () => {
    schedulePreviewRefresh();
});
const { colourErrors, hasColourErrors } = useSkinConfigContrastValidation(editableConfigs);
const { schedulePreviewRefresh } = useSkinPreview(editableConfigs, props.routes.preview);

/**
 * Scroll to the highest colour field label with a validation error on the page.
 */
async function scrollToHighestColourError(): Promise<void> {
    await window.Vue.nextTick();
    const titleLabels = Array.from(document.querySelectorAll<HTMLElement>("[data-skin-config-colour-error-label]"));
    if (titleLabels.length === 0) {
        return;
    }
    const highestError = titleLabels.reduce((highest, element) => {
        const elementTop = element.getBoundingClientRect().top + window.scrollY;
        const highestTop = highest.getBoundingClientRect().top + window.scrollY;
        return elementTop < highestTop ? element : highest;
    });
    highestError.scrollIntoView({ behavior: "smooth", block: "start" });
}

/**
 * Persist the toggle state immediately, then reload the page so server-rendered assets are reapplied.
 */
async function onToggleChanged(): Promise<void> {
    if (isProcessing.value) {
        return;
    }
    const previousToggleEnabled = !editableConfigs.value.toggle.enabled;
    const toast = window.sbProps.toastActivate("Saving...");
    try {
        isProcessing.value = true;
        loadError.value = null;
        const response = await apiRequest({
            url: props.routes.post,
            method: "POST",
            body: {
                configs: {
                    toggle: editableConfigs.value.toggle,
                },
            },
        });
        if (!response.success) {
            throw new Error(response.message || "Failed to save");
        }
        window.location.reload();
    } catch (error) {
        editableConfigs.value.toggle.enabled = previousToggleEnabled;
        const errorMessage = error instanceof Error ? error.message : "There was an unexpected error";
        loadError.value = errorMessage;
        toast.timeout({ text: errorMessage, isSuccess: false });
        await initialiseColourPickers();
    } finally {
        isProcessing.value = false;
    }
}

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

    if (editableConfigs.value.toggle.enabled && hasColourErrors.value) {
        await scrollToHighestColourError();
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
                configs: editableConfigs.value,
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

/**
 * Save immediately to ensure any invalid colours are
 * corrected and saved values are consistent with the form state
 */
window.Vue.onMounted(() => {
    if (editableConfigs.value.toggle.enabled && hasInvalidColours.value) {
        void onSaveSelected();
    }
});
</script>

<template>
    <div data-test="skin-config-container" class="u-flex-col l-gap-l l-pad-m--horiz">
        <div class="u-flex-col l-gap-m">
            <img :src="sonarImgSrc" alt="" class="l-c-cr" aria-hidden="true" />
            <p class="u-visually-hidden">
                Sonar UI is now available. We encourage you to enable it on your Staging server now to review your skin
                settings and custom CSS before it becomes the only option in v26.2.
            </p>
            <a href="/help/sso/send?returnUrl=/homepage/5808" target="_blank" rel="noopener noreferrer" class="button"
                >Read the Transition Guide</a
            >
        </div>
        <div v-if="loadError" data-test="skin-config-load-error">
            <request-error :further-info="loadError" />
        </div>
        <form method="post" class="u-flex-col l-gap-l skin-config-form" @submit.prevent="onSaveSelected">
            <div class="c-island l-pad-m">
                <label id="skin-config-toggle" class="inline-block" for="skin-config-toggle-field">
                    <span>{{ editableConfigs.toggle.title }}</span>
                    <span
                        v-if="editableConfigs.toggle.description"
                        data-tooltip
                        class="hint has-tip tip-bottom"
                        :title="editableConfigs.toggle.description"
                        >?</span
                    >
                </label>
                <div class="switch f-no-margins">
                    <input
                        id="skin-config-toggle-field"
                        v-model="editableConfigs.toggle.enabled"
                        type="checkbox"
                        name="advanced_ui_enabled"
                        autocomplete="off"
                        data-test="skin-config-toggle-input"
                        :disabled="isProcessing"
                        @change="onToggleChanged"
                    />
                    <label for="skin-config-toggle-field" data-test="skin-config-toggle">
                        <span>On</span>
                        <span>Off</span>
                    </label>
                </div>
            </div>
            <sc-categories
                :configs="editableConfigs"
                :colour-errors="colourErrors"
                @preview-refresh="schedulePreviewRefresh"
            />
            <sbx-label
                v-if="editableConfigs.toggle.enabled && hasColourErrors"
                size="l"
                sentiment="alert"
                icon="alert"
                text="Resolve all colour contrast issues to save the Skin Config."
                is-full-width
            />
            <div class="u-flex u-flex--justify l-gap-xs">
                <button v-if="hasUnsavedChanges" type="button" class="button u-flex__item--1" @click="onUndoChanges">
                    Undo Changes
                </button>
                <button class="button submit u-flex__item--1" data-test="skin-config-save-submit">
                    {{ saveText }}
                </button>
            </div>
        </form>
    </div>
</template>

<style scoped></style>
