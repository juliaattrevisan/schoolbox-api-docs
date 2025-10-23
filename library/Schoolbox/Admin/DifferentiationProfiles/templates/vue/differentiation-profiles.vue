<script setup lang="ts">
/**
 * To configure differentiation profiles
 */

import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { Data } from "../js/types/differentiationProfilesTypes";

const props = defineProps<{
    postRoute: string;
    data: Data;
    returnUrl?: string;
}>();

const data = window.Vue.ref<Data>(props.data);

// Get headers as an ordered array for consistent column sizing
const headersArray = window.Vue.computed(() => {
    if (!data.value.headers) return [];
    return Object.entries(data.value.headers).map(([headerKey, header]) => ({
        headerKey,
        ...header,
    }));
});

// #region save

const isProcessing = window.Vue.ref(false);
const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create

/**
 * Get the data to be posted to the server
 * @returns post data object
 */
function getPostData(): Record<string, unknown> {
    return {
        enabled: data.value.featureToggle.enabled ? 1 : 0,
        modules: data.value.modules,
    };
}

/**
 * pass the new latest details to the backend
 */
async function onSaveSelected(): Promise<void> {
    const toast = window.sbProps.toastActivate("Saving...");
    try {
        isProcessing.value = true;
        loadError.value = null;
        const response = await apiRequest<string, Record<string, unknown>>({
            url: props.postRoute,
            method: "POST",
            body: getPostData(),
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
                    <fieldset v-for="(differentiationProfile, key) in data.modules" :key="key" class="content">
                        <ul class="information-list sortable ui-sortable">
                            <template v-for="module in differentiationProfile.fields" :key="module.id">
                                <li class="list-item-container">
                                    <div class="list-item">
                                        <template v-for="header in headersArray" :key="header.headerKey">
                                            <div v-if="header" :class="`small-12 medium-${header.colSpan} column`">
                                                <!-- Toggle column -->
                                                <div v-if="header.type === 'toggle'" class="switch f-no-margins">
                                                    <input
                                                        :id="`differentiation-profiles-adjustments-field-${module.id}`"
                                                        v-model="module.is_enabled"
                                                        type="checkbox"
                                                        :name="`modules[${key}][${module.name}][is_enabled]`"
                                                    />
                                                    <label
                                                        :for="`differentiation-profiles-adjustments-field-${module.id}`"
                                                    >
                                                        <span>On</span>
                                                        <span>Off</span>
                                                    </label>
                                                </div>
                                                <!-- Text columns -->
                                                <div v-else-if="header.type === 'text'">
                                                    <label
                                                        :for="`module-${header.key}-${module.id}`"
                                                        class="t-visually-hidden"
                                                    >
                                                        {{ header.title || header.key }}
                                                    </label>
                                                    <input
                                                        :id="`module-${header.key}-${module.id}`"
                                                        v-model="module[header.key]"
                                                        type="text"
                                                        :name="`modules[${key}][${module.name}][${header.key}]`"
                                                        :readonly="header.key === 'title'"
                                                    />
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </fieldset>
                </section>
            </Transition>
            <div class="row">
                <div class="small-12 column">
                    <input type="submit" name="btnSubmit" value="Save" />
                </div>
            </div>
        </form>
    </div>
</template>

<style scoped></style>
