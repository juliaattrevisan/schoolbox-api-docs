<script setup lang="ts">
/**
 * A specific available item in the fields palette that can be clicked on or dragged into the selected fields
 */

import { useProfileEditorFieldsStore } from "./store/profileEditorFieldsStore";
import type { ProfileEditorAvailableField } from "./types/profileEditorTypes";
import PeFieldsIcon from "./pe-fields-icon.vue";

interface Props {
    field: ProfileEditorAvailableField;
}

const props = defineProps<Props>();
const peFieldsStore = useProfileEditorFieldsStore();

// Prevent the user from selecting a field that is already selected
const disabled = window.Vue.computed(() => peFieldsStore.getActiveFieldType(props.field.field) !== null);
</script>

<template>
    <div
        class="pe-palette-item l-pad-s"
        :class="{ disabled }"
        :title="disabled ? 'This field is already selected' : undefined"
        :data-pe-prevent-drag="disabled ? true : undefined"
        :data-test="peFieldsStore.getFieldIdentifier(props.field)"
        @click="peFieldsStore.enableField(props.field.field)"
    >
        <pe-fields-icon :field="props.field" />
        {{ peFieldsStore.getFieldName(props.field) }}
    </div>
</template>

<style scoped lang="scss">
.pe-palette-item {
    background-color: #ffffff;
    height: 100%; // To avoid a gap between the items

    &.disabled {
        cursor: not-allowed;
        opacity: 0.7;
    }

    &:not(.disabled) {
        cursor: grab;
        color: var(--content-ui-foreground);

        /* Hover effect on the items in the palette */
        &:hover {
            transform: translate(0.2rem, -0.2rem);
            box-shadow: -10px 10px 0 rgba(0, 0, 0, 0.1);
            z-index: var(--sbx-depth-ui-overlay);
        }
    }
}
</style>
