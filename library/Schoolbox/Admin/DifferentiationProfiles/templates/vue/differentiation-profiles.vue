<script setup lang="ts">
/**
 * To configure differentiation profiles
 */

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
</script>

<template>
    <div class="small-12 island">
        <form method="post" :action="postRoute">
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
            <section>
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
                    <template v-for="module in differentiationProfile.fields" :key="module.id">
                        <template v-for="header in headersArray" :key="header.headerKey">
                            <div v-if="header" :class="`small-12 medium-${header.colSpan} columns`">
                                <!-- Toggle column -->
                                <div v-if="header.type === 'toggle'" class="switch f-no-margins">
                                    <input
                                        :id="`differentiation-profiles-adjustments-field-${module.id}`"
                                        v-model="module.is_enabled"
                                        type="checkbox"
                                        :name="`modules[${key}][${module.name}][is_enabled]`"
                                    />
                                    <label :for="`differentiation-profiles-adjustments-field-${module.id}`">
                                        <span>On</span>
                                        <span>Off</span>
                                    </label>
                                </div>
                                <!-- Text columns -->
                                <div v-else-if="header.type === 'text'">
                                    <label :for="`module-${header.key}-${module.id}`" class="t-visually-hidden">
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
                        <input type="hidden" :name="`modules[${key}][${module.name}][name]`" :value="module.name" />
                        <input type="hidden" :name="`modules[${key}][${module.name}][id]`" :value="module.id" />
                    </template>
                </fieldset>
            </section>
            <div class="row">
                <div class="small-12 column">
                    <input type="hidden" name="returnUrl" :value="props.returnUrl" />
                    <input type="submit" name="btnSubmit" value="Save" />
                </div>
            </div>
        </form>
    </div>
</template>

<style scoped></style>
