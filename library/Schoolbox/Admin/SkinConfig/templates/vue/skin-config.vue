<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer.vue";
import type { Routes, SkinConfigData } from "../js/types/skinConfigTypes";
import { useSkinPreview } from "./composable/useSkinPreview";

const props = defineProps<{
    routes: Routes;
    configs: SkinConfigData;
}>();

const savedConfigs = window.Vue.ref(cloneConfigs(props.configs));
const configs = window.Vue.ref<SkinConfigData>(cloneConfigs(props.configs));
const saveContainer = window.Vue.ref<HTMLDivElement | null>(null);
const isProcessing = window.Vue.ref(false);
const isAtBottomOfPage = window.Vue.ref(false);
const loadError = window.Vue.ref<string | null>(null);
const initialConfigSnapshot = window.Vue.ref(serializeConfigs(savedConfigs.value));
const saveText = window.Vue.computed(() => (isProcessing.value ? "Saving..." : "Save"));
const currentConfigSnapshot = window.Vue.computed(() => serializeConfigs(configs.value));
const hasUnsavedChanges = window.Vue.computed(() => initialConfigSnapshot.value !== currentConfigSnapshot.value);
const showSaveDrawer = window.Vue.computed(() => hasUnsavedChanges.value || isAtBottomOfPage.value);
const { schedulePreviewRefresh } = useSkinPreview(configs, props.routes.preview);
let intersectionObserver: IntersectionObserver | null = null;

/**
 * Clone the config state so form edits do not mutate the saved baseline.
 * @param value - The current Skin Config payload.
 * @returns A deep-cloned Skin Config payload.
 */
function cloneConfigs(value: SkinConfigData): SkinConfigData {
    return JSON.parse(serializeConfigs(value)) as SkinConfigData;
}

/**
 * Serialize the current config state for dirty-state comparison.
 * @param value - The current Skin Config payload.
 * @returns A stable serialized snapshot for dirty-state comparison.
 */
function serializeConfigs(value: SkinConfigData): string {
    return JSON.stringify(value);
}

/**
 * Restore the last saved config state.
 * @returns A promise that resolves once the form state and preview have been reset.
 */
async function onUndoChanges(): Promise<void> {
    if (isProcessing.value) {
        return;
    }
    loadError.value = null;
    configs.value = cloneConfigs(savedConfigs.value);
    await initialiseColourPickers();
    schedulePreviewRefresh();
}

/**
 * Track whether the bottom save area is currently visible on screen.
 */
function initIntersectionObserver(): void {
    if (saveContainer.value === null) {
        return;
    }
    intersectionObserver = new IntersectionObserver((elements) => {
        isAtBottomOfPage.value = elements[0].intersectionRatio !== 0;
    });
    intersectionObserver.observe(saveContainer.value);
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
        savedConfigs.value = cloneConfigs(configs.value);
        initialConfigSnapshot.value = serializeConfigs(savedConfigs.value);
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
    initIntersectionObserver();
    void initialiseColourPickers();
});

window.Vue.onBeforeUnmount(() => {
    intersectionObserver?.disconnect();
});
</script>

<template>
    <div data-test="skin-config-container">
        <form method="post" class="skin-config-form" @submit.prevent="onSaveSelected">
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
