<script setup lang="ts">
/**
 * Vue component for rendering and managing the Skin Config form in the Schoolbox Admin interface.
 * This component handles displaying the configuration options, tracking changes, and saving updates to the backend.
 */

import { apiRequest } from "@js/util/sbx/apiUtils";
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import type { Routes, SkinConfigData } from "../js/types/skinConfigTypes";
import { useSkinConfigState } from "./composable/useSkinConfigState";
import { useSkinPreview } from "./composable/useSkinPreview";

const props = defineProps<{
    routes: Routes;
    configs: SkinConfigData;
}>();

const isProcessing = window.Vue.ref(false);
const loadError = window.Vue.ref<string | null>(null);
const saveText = window.Vue.computed(() => (isProcessing.value ? "Saving..." : "Save"));
const { configs, saveContainer, hasUnsavedChanges, showSaveDrawer, resetToSavedState, markCurrentAsSaved } =
    useSkinConfigState(props.configs, isProcessing, () => {
        schedulePreviewRefresh();
    });
const { schedulePreviewRefresh } = useSkinPreview(configs, props.routes.preview);
const activeConfigs = window.Vue.computed(() =>
    configs.value.toggle.enabled ? configs.value.sonar : configs.value.existing,
);

window.Vue.watch(
    () => configs.value.toggle.enabled,
    () => {
        schedulePreviewRefresh();
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
    <div data-test="skin-config-container">
        <div v-if="loadError" class="small-12 columns" data-test="custom-participation-load-error">
            <request-error :further-info="loadError" />
        </div>
        <form method="post" class="skin-config-form" @submit.prevent="onSaveSelected">
            <section>
                <fieldset class="content">
                    <div class="small-12 columns">
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
                </fieldset>
            </section>
            <div
                v-for="(subcategories, categoryKey) in activeConfigs"
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
                        v-for="(subcategory, index) in subcategories"
                        :key="subcategory.name || `default-${index}`"
                        class="content"
                        :data-test="`skin-config-subcategory-${subcategory.name || `default-${index}`}`"
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
                                    : 'small-12 medium-6 large-4 l-mar-m--bottom columns',
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
            <div ref="saveContainer" class="save-drawer-anchor">
                <bottom-drawer :show-drawer="showSaveDrawer">
                    <div class="l-flex l-flex--justify l-gap-xs">
                        <button
                            v-if="hasUnsavedChanges"
                            type="button"
                            class="button l-flex__item--1"
                            @click="onUndoChanges"
                        >
                            Undo Changes
                        </button>
                        <button
                            name="btnSubmit"
                            class="button submit l-flex__item--1"
                            data-test="skin-config-save-submit"
                        >
                            {{ saveText }}
                        </button>
                    </div>
                </bottom-drawer>
            </div>
        </form>
    </div>
</template>

<style scoped>
.skin-config-form {
    padding-bottom: 100px;
}

.save-drawer-anchor {
    min-height: 30px;
}
</style>
