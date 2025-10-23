<script setup lang="ts">
/**
 * To configure differentiation profiles
 */

import type { Data } from "../js/types/differentiationProfilesTypes";

const props = defineProps<{
    postRoute: string;
    data: Data;
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
                            />
                            <label for="differentiation-profiles-field">
                                <span>On</span>
                                <span>Off</span>
                            </label>
                        </div>
                    </div>
                </fieldset>
            </section>
            <div class="row">
                <div class="small-12 column">
                    <input type="submit" value="Save" />
                </div>
            </div>
        </form>
    </div>
</template>

<style scoped></style>
