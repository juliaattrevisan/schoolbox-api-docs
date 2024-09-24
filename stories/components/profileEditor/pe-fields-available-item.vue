<script setup lang="ts">
/**
 * A specific available item in the fields palette that can be clicked on or dragged into the selected fields
 */

import { useProfileEditorStore } from "./store/profileEditorStore";
import type { ProfileEditorAvailableField } from "./types/profileEditorTypes";

interface Props {
    field: ProfileEditorAvailableField;
}

const props = defineProps<Props>();
const peStore = useProfileEditorStore();

const isDisabled = window.Vue.computed(() => {
    if (peStore.selectedRoleType === null) return true;
    return peStore.selectedRoleType.fields.selected.some((f) => f.field === props.field.field);
});

/**
 * Set the field as selected
 */
function onSelected(): void {
    if (isDisabled.value) return;
    if (peStore.selectedRoleType === null) return;

    const fieldSettingsKeys = peStore.fieldSettings.map((setting) => setting.key);
    const emptyFieldSettings = Object.fromEntries(fieldSettingsKeys.map((key) => [key, []]));

    peStore.selectedRoleType.fields.selected.unshift({
        field: props.field.field,
        displayAs: props.field.displayAs,
        fieldSettings: emptyFieldSettings,
    });
}
</script>

<template>
    <div
        class="large-2 medium-4 small-6 l-pad-s pe-palette-item"
        :class="{
            disabled: isDisabled,
        }"
        :title="isDisabled ? 'This field is already selected' : undefined"
        @click="onSelected"
    >
        {{ props.field.displayAs }}
    </div>
</template>

<style scoped lang="scss">
.pe-palette-item {
    &.disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }

    &:not(.disabled) {
        cursor: grab;

        /* Hover effect on the items in the palette */
        &:hover {
            transform: translate(0.2rem, -0.2rem);
            box-shadow: -10px 10px 0 rgba(0, 0, 0, 0.1);
            z-index: var(--sbx-depth-ui-overlay);
        }
    }
}
</style>
