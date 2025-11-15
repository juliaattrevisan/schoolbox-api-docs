<script setup lang="ts">
/**
 * Display adjustment levels, either Adjustment Category or Adjustment Level
 */

import EmptyState from "@schoolboxVue/components/empty-state.vue";
import type { Profile, ProfileAdjustmentKeys, AdjustmentType } from "../../js/types/manageDifferentiationProfilesTypes";
import { useMdpSaveStore } from "../store/mdpSaveStore";
import MdpGridCellAction from "./mdp-grid-cell-action.vue";

const props = defineProps<{
    profile: Profile;
    headerKey: ProfileAdjustmentKeys;
    adjustments: number[] | null;
    options: AdjustmentType[];
}>();

const mdpSaveStore = useMdpSaveStore();
// const hasChanged = window.Vue.computed(() => {
//     return currentText.value !== props.text;
// });
const hasChanged = window.Vue.ref(false);
const isSaving = window.Vue.ref(false);
const isFocused = window.Vue.ref(false);
const selectedAdjustments = window.Vue.ref<number[]>(props.adjustments ?? []);

/**
 * When the add adjustment button is clicked.
 */
function onAdd(): void {
    // open modal
}

/**
 * When the modify button is clicked.
 */
function onModify(): void {
    isFocused.value = true;
}

/**
 * When the save button is clicked.
 */
function onSave(): void {
    const { profile } = props;
    profile[props.headerKey] = selectedAdjustments.value;
    void mdpSaveStore.save(profile);
}
</script>

<template>
    <div>
        <div v-if="isFocused">
            <fieldset class="checklist">
                <legend>Adjustments</legend>
                <ul class="option-list">
                    <li v-for="option in options" :key="option.id">
                        <input
                            :id="`checkbox-${option.id}-${headerKey}-${props.profile.student.id}`"
                            v-model="selectedAdjustments"
                            :value="option.id"
                            type="checkbox"
                        /><label :for="`checkbox-${option.id}-${headerKey}-${props.profile.student.id}`">{{
                            option.name
                        }}</label>
                    </li>
                </ul>
            </fieldset>
        </div>
        <empty-state v-else-if="adjustments === null" icon="icon-list">
            <button @click.prevent="onAdd">Add adjustment</button>
        </empty-state>
        <div v-else class="l-pad-s">
            <ul>
                <li v-for="adjustment in adjustments" :key="adjustment">
                    {{ options.find((option) => option.id === adjustment)?.name }}
                </li>
            </ul>
        </div>
    </div>
    <mdp-grid-cell-action
        :profile="props.profile"
        :header-key="props.headerKey"
        :has-changed="hasChanged"
        :is-saving="isSaving"
        :is-focused="isFocused"
        @modify="onModify"
        @save="onSave"
    />
</template>

<style scoped></style>
