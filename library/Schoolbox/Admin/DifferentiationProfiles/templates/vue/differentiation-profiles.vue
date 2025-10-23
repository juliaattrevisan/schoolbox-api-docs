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
                        <div class="switch">
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
            <section v-for="(differentiationProfile, key) in data.modules" :key="key">
                <fieldset class="content">
                    <template v-for="module in differentiationProfile.fields" :key="module.id">
                        <div class="small-12 medium-1 columns">
                            <div class="switch">
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
                        </div>
                        <div class="small-12 medium-3 columns">
                            <label :for="`module-title-${module.id}`" class="t-visually-hidden">Title</label>
                            <input
                                :id="`module-title-${module.id}`"
                                v-model="module.title"
                                type="text"
                                :name="`modules[${key}][${module.name}][title]`"
                                readonly
                            />
                        </div>
                        <div class="small-12 medium-3 columns">
                            <label :for="`module-terminology-${module.id}`" class="t-visually-hidden"
                                >Terminology</label
                            >
                            <input
                                :id="`module-terminology-${module.id}`"
                                v-model="module.terminology"
                                type="text"
                                :name="`modules[${key}][${module.name}][terminology]`"
                            />
                        </div>
                        <div class="small-12 medium-5 columns">
                            <label :for="`module-description-${module.id}`" class="t-visually-hidden"
                                >Description</label
                            >
                            <input
                                :id="`module-description-${module.id}`"
                                v-model="module.description"
                                type="text"
                                :name="`modules[${key}][${module.name}][description]`"
                            />
                        </div>
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
