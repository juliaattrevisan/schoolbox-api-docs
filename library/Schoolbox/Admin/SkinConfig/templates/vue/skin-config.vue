<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { Routes, SkinConfigData } from "../js/types/skinConfigTypes";

const PREVIEW_DEBOUNCE_MS = 300;
const PREVIEW_STYLE_ID = "skin-config-preview-style";

const props = defineProps<{
    routes: Routes;
    configs: SkinConfigData;
}>();

const configs = window.Vue.ref<SkinConfigData>(props.configs);
const isProcessing = window.Vue.ref(false);
const loadError = window.Vue.ref<string | null>(null);
const saveText = window.Vue.computed(() => (isProcessing.value ? "Saving..." : "Save"));
let previewAbortController: AbortController | null = null;
let previewDebounceTimer: number | null = null;
let previewRequestSequence = 0;

/**
 * Convert backend category keys to display titles for the page.
 * @param categoryKey The backend category key.
 * @returns The display title for the category.
 */
function getCategoryTitle(categoryKey: string): string {
    return (
        {
            colours: "Colours",
            "custom css": "Custom CSS",
        }[categoryKey] || categoryKey
    );
}

/**
 * Ensure the preview stylesheet element exists.
 * @returns The preview style element.
 */
function getPreviewStyleElement(): HTMLStyleElement {
    const existingElement = document.getElementById(PREVIEW_STYLE_ID);
    if (existingElement instanceof HTMLStyleElement) {
        return existingElement;
    }
    const styleElement = document.createElement("style");
    styleElement.id = PREVIEW_STYLE_ID;
    document.head.append(styleElement);

    return styleElement;
}

/**
 * Remove any pending or active preview request state.
 */
function cancelPendingPreview(): void {
    if (previewDebounceTimer !== null) {
        window.clearTimeout(previewDebounceTimer);
        previewDebounceTimer = null;
    }
    previewAbortController?.abort();
    previewAbortController = null;
}

/**
 * Remove the injected preview stylesheet.
 */
function removePreviewStyle(): void {
    const existingElement = document.getElementById(PREVIEW_STYLE_ID);
    if (existingElement instanceof HTMLStyleElement) {
        existingElement.remove();
    }
}

/**
 * Request the latest preview stylesheet from the backend and apply it.
 */
async function requestPreviewCss(): Promise<void> {
    previewAbortController?.abort();
    const abortController = new AbortController();
    previewAbortController = abortController;
    const requestSequence = ++previewRequestSequence;
    try {
        const response = await fetch(props.routes.preview, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "text/css, application/json",
            },
            body: JSON.stringify({
                configs: configs.value,
            }),
            signal: abortController.signal,
        });
        if (!response.ok) {
            return;
        }
        const css = await response.text();
        if (requestSequence !== previewRequestSequence || abortController.signal.aborted) {
            return;
        }
        getPreviewStyleElement().textContent = css;
    } catch (error) {
        if (!(error instanceof DOMException) || error.name !== "AbortError") {
            return;
        }
    } finally {
        if (previewAbortController === abortController) {
            previewAbortController = null;
        }
    }
}

/**
 * Debounce preview regeneration while the user is editing.
 */
function schedulePreviewRefresh(): void {
    if (previewDebounceTimer !== null) {
        window.clearTimeout(previewDebounceTimer);
    }
    previewDebounceTimer = window.setTimeout(() => {
        previewDebounceTimer = null;
        void requestPreviewCss();
    }, PREVIEW_DEBOUNCE_MS);
}

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

/**
 * Clean up any pending preview requests or styles when the component is unmounted to prevent memory leaks or orphaned styles.
 */
window.Vue.onBeforeUnmount(() => {
    cancelPendingPreview();
    removePreviewStyle();
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
                            <h2 class="subheader">{{ getCategoryTitle(categoryKey) }}</h2>
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
