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

const disabled = window.Vue.computed(() => peStore.isFieldDisabled(props.field.field));
</script>

<template>
    <div
        class="large-2 medium-4 small-6 l-pad-s pe-palette-item"
        :class="{ disabled }"
        :title="disabled ? 'This field is already selected' : undefined"
        :data-pe-field="props.field.field"
        @click="peStore.addSelectedField(props.field.field)"
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
