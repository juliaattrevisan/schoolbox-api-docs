<script setup lang="ts">
/**
 * To configure differentiation profiles
 */

import type Sortable from "sortablejs";
import SortableWrapper from "@schoolboxVue/components/sortable-wrapper.vue";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { convertObjectToArray } from "@js/util/jsUtils";
import type { Data, Header, Field } from "../js/types/adminDifferentiationProfilesTypes";

const props = defineProps<{
    postRoute: string;
    data: Data;
    returnUrl?: string;
}>();

const data = window.Vue.ref<Data>(props.data);

// We need to maintain a separate reactive list of fields for each module to work with the sortable component, as it requires an array. We will keep this in sync with the original data.modules object.
const moduleFieldLists = window.Vue.reactive<Record<string, Field[]>>({});
window.Vue.watchEffect(() => {
    Object.keys(data.value.modules).forEach((moduleKey) => {
        if (!moduleFieldLists[moduleKey]) {
            moduleFieldLists[moduleKey] = convertObjectToArray(data.value.modules[moduleKey].fields) as Field[];
        }
    });
});

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
 * @param moduleKey The key of the module whose fields are being resequenced
 */
function onResequenceField(event: Sortable.SortableEvent, moduleKey: string): void {
    if (event.oldIndex === event.newIndex) return;
    if (event.oldIndex === undefined || event.newIndex === undefined) return;

    const list = moduleFieldLists[moduleKey];
    if (!list) return;

    const [moved] = list.splice(event.oldIndex, 1);
    list.splice(event.newIndex, 0, moved);

    list.forEach((field, index) => {
        data.value.modules[moduleKey].fields[field.name].sequence = index;
    });
}

// #endregion resequence

// #region save

const isProcessing = window.Vue.ref(false);
const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create
const saveText = window.Vue.computed(() => (isProcessing.value ? "Saving..." : "Save"));

/**
 * Prepare the request body with cleaned up modules data
 * @returns The request body object with enabled flag and cleaned modules
 */
function getBody(): { enabled: number; modules: Data["modules"] } {
    const cleanedModules = { ...data.value.modules };
    Object.keys(cleanedModules).forEach((moduleKey) => {
        const module = cleanedModules[moduleKey];
        if (module.fields) {
            Object.keys(module.fields).forEach((fieldKey) => {
                const field = module.fields[fieldKey];
                if (field.terminology === "") {
                    field.terminology = null;
                }
                if (field.description === "") {
                    field.description = null;
                }
            });
        }
    });
    return {
        enabled: data.value.featureToggle.enabled ? 1 : 0,
        modules: cleanedModules,
    };
}

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

        const response = await apiRequest({
            url: props.postRoute,
            method: "POST",
            body: getBody(),
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
    <div class="small-12 island" data-test="differentiation-profiles-container">
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
                                autocomplete="off"
                            />
                            <label for="differentiation-profiles-field" data-test="differentiation-profiles-toggle">
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
                        <div class="sortable">
                            <header class="list-header">
                                <div class="list-item">
                                    <div
                                        v-for="(header, headerKey) in data.headers"
                                        :key="headerKey"
                                        :class="`small-12 medium-${header.colSpan} column`"
                                    >
                                        <label
                                            class="f-no-margins"
                                            :data-test="`differentiation-profiles-header-${header.title}`"
                                            >{{ header.title ? header.title : "&nbsp;" }}</label
                                        >
                                    </div>
                                </div>
                            </header>
                        </div>
                    </fieldset>
                    <fieldset
                        v-for="(module, key) in data.modules"
                        :key="key"
                        class="content"
                        :data-test="`differentiation-profiles-fieldset-${key}`"
                    >
                        <sortable-wrapper
                            tag="ul"
                            class="information-list sortable ui-sortable"
                            :list="moduleFieldLists[key]"
                            :item-key="(item) => item.id"
                            :options="sortableOptions"
                            :transition-enter="slideDownOnEnter"
                            :transition-leave="slideUpOnLeave"
                            @end="(e) => onResequenceField(e, key as string)"
                        >
                            <template #item="item">
                                <li
                                    class="list-item-container"
                                    :data-module-key="key"
                                    :data-field-key="item.element.name"
                                    :class="{ dragging: item.isDragging }"
                                >
                                    <div class="l-flex">
                                        <div
                                            v-if="module.isSortable"
                                            class="grip compact"
                                            data-dp-drag-handle
                                            :data-test="`differentiation-profiles-drag-handle-${item.element.id}`"
                                        />
                                        <div class="list-item">
                                            <template
                                                v-for="header in (convertObjectToArray(data.headers) as Header[]) || []"
                                                :key="header.key"
                                            >
                                                <div :class="`small-12 medium-${header.colSpan} column`">
                                                    <template v-if="header.type === 'checkbox'">
                                                        <input
                                                            :id="`field-${header.key}-${item.element.id}`"
                                                            v-model="item.element.is_enabled"
                                                            type="checkbox"
                                                            :data-test="`differentiation-profiles-input-${header.key}-${item.element.id}`"
                                                        />
                                                        <label
                                                            :for="`field-${header.key}-${item.element.id}`"
                                                            class="checkbox-label"
                                                            :data-test="`differentiation-profiles-input-${header.key}-${item.element.id}-label`"
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
                                                            :maxlength="header.maxLength || undefined"
                                                            :data-test="`differentiation-profiles-input-${header.key}-${item.element.id}`"
                                                        />
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
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
