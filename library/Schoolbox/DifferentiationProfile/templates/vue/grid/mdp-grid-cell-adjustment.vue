<script setup lang="ts">
/**
 * Display adjustment levels, either Adjustment Category or Adjustment Level
 */

import EmptyState from "@schoolboxVue/components/empty-state.vue";
import type { Profile, ProfileAdjustmentKeys, AdjustmentType } from "../../js/types/manageDifferentiationProfilesTypes";
import { useMdpHeaderStore } from "../store/mdpHeaderStore";
import { useMdpSaveStore } from "../store/mdpSaveStore";
import MdpGridCellAction from "./mdp-grid-cell-action.vue";

const props = defineProps<{
    profile: Profile;
    headerKey: ProfileAdjustmentKeys;
    adjustments: number[] | null;
    options: AdjustmentType[];
}>();

const mdpHeaderStore = useMdpHeaderStore();
const mdpSaveStore = useMdpSaveStore();
const selectedAdjustments = window.Vue.ref<number[]>(props.adjustments ?? []);
const hasChanged = window.Vue.computed(() => {
    return selectedAdjustments.value.join(",") !== (props.adjustments ?? []).join(",");
});
const isSaving = window.Vue.ref(false);
const isFocused = window.Vue.ref(false);

/**
 * When the add adjustment button is clicked.
 */
function onAdd(): void {
    isFocused.value = true;
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
async function onSave(): Promise<void> {
    isSaving.value = true;
    const { profile } = props;
    profile[props.headerKey] = selectedAdjustments.value.length > 0 ? selectedAdjustments.value : null;
    await mdpSaveStore.save(profile);
    isFocused.value = false;
    isSaving.value = false;
}
</script>

<template>
    <div class="cell-container" :style="{ border: isFocused ? '1px solid var(--form-border-color)' : 'none' }">
        <div v-if="isFocused">
            <div class="checklist l-pad-s">
                <legend class="t-visually-hidden">
                    {{ mdpHeaderStore.getTitleByKey(props.headerKey) }} for {{ props.profile.student.givenName }}
                    {{ props.profile.student.lastName }}
                </legend>
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
            </div>
        </div>
        <empty-state v-else-if="adjustments === null" icon="icon-list">
            <button @click.prevent="onAdd">Add {{ mdpHeaderStore.getTitleByKey(props.headerKey) }}</button>
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

<style scoped>
.cell-container {
    display: flex;
}
</style>
