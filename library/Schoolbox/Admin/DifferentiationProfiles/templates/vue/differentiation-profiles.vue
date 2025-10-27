<script setup lang="ts">
/**
 * To configure differentiation profiles
 */

import type Sortable from "sortablejs";
import SortableWrapper from "@schoolboxVue/components/sortable-wrapper.vue";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { convertObjectToArray } from "@js/util/jsUtils";
import type { Data, Header } from "../js/types/differentiationProfilesTypes";

const props = defineProps<{
    postRoute: string;
    data: Data;
    returnUrl?: string;
}>();

const data = window.Vue.ref<Data>(props.data);

// #region resequence

const sortableOptions = window.Vue.computed(() => ({
    forceFallback: true, // We use this to force the html5 drag and drop logic to be ignored and use the fallback so we can style it
    fallbackClass: "dragged-item", // The class to apply to the clone when dragging
    animation: 200, // How long the animation should take when items shift
    handle: "[data-dp-drag-handle]", // The handle to use to drag the field around
}));

/**
 * When the user resequences the fields, update the sequence of
 * each field in the relevant module
 * @param event The event that triggered the resequence
 */
function onResequenceField(event: Sortable.SortableEvent): void {
    const moduleKey = event.item.getAttribute("data-module-key");
    if (moduleKey === null) {
        return;
    }
    const fields = document.querySelectorAll(`[data-module-key="${moduleKey}"]`);
    const fieldKeys = Array.from(fields).map((fieldElement) => fieldElement.getAttribute("data-field-key"));
    fieldKeys.forEach((fieldKey, index) => {
        data.value.modules[moduleKey].fields[fieldKey!].sequence = index;
    });
}

// #endregion resequence

// #region save

const isProcessing = window.Vue.ref(false);
const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create
const saveText = window.Vue.computed(() => (isProcessing.value ? "Saving..." : "Save"));

/**
 * pass the new latest details to the backend
 */
async function onSaveSelected(): Promise<void> {
    if (isProcessing.value) {
        return;
    }
    const toast = window.sbProps.toastActivate("Saving...");
    try {
        isProcessing.value = true;
        loadError.value = null;
        console.warn(data.value.modules);
        const response = await apiRequest({
            url: props.postRoute,
            method: "POST",
            body: {
                enabled: data.value.featureToggle.enabled ? 1 : 0,
                modules: data.value.modules,
            },
        });
        if (!response.success) {
            throw new Error(response.message || "Failed to save");
        }
        toast.timeout({ text: "Differentiation profiles successfully updated" });
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : "There was an unexpected error";
        loadError.value = errorMessage;
        toast.timeout({ text: errorMessage, isSuccess: false });
    } finally {
        isProcessing.value = false;
    }
}

// #endregion save
</script>

<template>
    <div class="small-12 island">
        <form method="post" @submit.prevent="onSaveSelected">
            <section>
                <fieldset class="content">
                    <div class="small-12 columns">
                        <label id="differentiation-profiles" class="inline-block" for="differentiation-profiles-field">
                            <span :class="{ 'required-with-tip': data.featureToggle.required }">{{
                                data.featureToggle.title
                            }}</span>
                            <span
                                v-if="data.featureToggle.description"
                                data-tooltip
                                class="hint has-tip tip-bottom"
                                :title="data.featureToggle.description"
                                >?</span
                            >
                        </label>
                        <div class="switch f-no-margins">
                            <input
                                id="differentiation-profiles-field"
                                v-model="data.featureToggle.enabled"
                                type="checkbox"
                                name="enabled"
                            />
                            <label for="differentiation-profiles-field">
                                <span>On</span>
                                <span>Off</span>
                            </label>
                        </div>
                    </div>
                </fieldset>
            </section>
            <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
                <section v-if="data.featureToggle.enabled">
                    <fieldset v-if="data.headers && Object.keys(data.headers).length > 0" class="content">
                        <div class="row">
                            <div
                                v-for="(header, headerKey) in data.headers"
                                :key="headerKey"
                                :class="`small-12 medium-${header.colSpan} columns`"
                            >
                                <h4>{{ header.title ? header.title : "" }}</h4>
                            </div>
                        </div>
                    </fieldset>
                    <fieldset v-for="(module, key) in data.modules" :key="key" class="content">
                        <sortable-wrapper
                            tag="ul"
                            class="information-list sortable ui-sortable"
                            :list="convertObjectToArray(module.fields)"
                            :item-key="(item) => item.id"
                            :options="sortableOptions"
                            :transition-enter="slideDownOnEnter"
                            :transition-leave="slideUpOnLeave"
                            @end="onResequenceField"
                        >
                            <template #item="item">
                                <li
                                    class="list-item-container"
                                    :data-module-key="key"
                                    :data-field-key="item.element.name"
                                    :class="{ dragging: item.isDragging }"
                                >
                                    <div class="list-item">
                                        <div v-if="module.isSortable" class="grip compact" data-dp-drag-handle />
                                        <template
                                            v-for="header in (convertObjectToArray(data.headers) as Header[]) || []"
                                            :key="header.headerKey"
                                        >
                                            <div :class="`small-12 medium-${header.colSpan} column`">
                                                <template v-if="header.type === 'checkbox'">
                                                    <input
                                                        :id="`differentiation-profiles-adjustments-field-${item.element.id}`"
                                                        v-model="item.element.is_enabled"
                                                        type="checkbox"
                                                    />
                                                    <label
                                                        :for="`differentiation-profiles-adjustments-field-${item.element.id}`"
                                                        class="checkbox-label"
                                                        >{{ item.element.title }}</label
                                                    >
                                                </template>
                                                <template v-else-if="header.type === 'text'">
                                                    <label
                                                        :for="`field-${header.key}-${item.element.id}`"
                                                        class="t-visually-hidden"
                                                    >
                                                        {{ header.title || header.key }}
                                                    </label>
                                                    <input
                                                        :id="`field-${header.key}-${item.element.id}`"
                                                        v-model="item.element[header.key]"
                                                        type="text"
                                                        :readonly="header.key === 'title'"
                                                    />
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </li>
                            </template>
                        </sortable-wrapper>
                    </fieldset>
                </section>
            </Transition>
            <div class="row">
                <div class="small-12 column">
                    <input type="submit" name="btnSubmit" :value="saveText" />
                </div>
            </div>
        </form>
    </div>
</template>

<style scoped></style>
