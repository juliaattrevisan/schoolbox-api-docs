<script setup lang="ts">
/**
 * The selected fields for the current role type
 */

import type Sortable from "sortablejs";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import PeFieldsSelectedItem from "./pe-fields-selected-item.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";
import SortableWrapper from "./gsap-sortable-wrapper.vue";

const peStore = useProfileEditorStore();

const sortableOptions = window.Vue.computed(() => ({
    group: `fields-${peStore.selectedRoleType ? peStore.selectedRoleType.name : "-"}`,
    itemKey: "sequence",
    forceFallback: true,
    fallbackClass: "dragged-item",
    animation: 200,
    handle: "[data-pe-field-drag-handle]",
}));

/**
 * When the user resequences the fields
 * @param event The event that triggered the resequence
 */
function resequenceFields(event: Sortable.SortableEvent): void {
    if (peStore.selectedRoleType === null) return;
    if (event.oldIndex === event.newIndex) return;
    if (event.oldIndex === undefined || event.newIndex === undefined) return;

    const newFields = [...peStore.selectedRoleType.fields.selected];
    const movedField = newFields.splice(event.oldIndex, 1)[0];
    newFields.splice(event.newIndex, 0, movedField);
    peStore.selectedRoleType.fields.selected = newFields;
}
</script>

<template>
    <template v-if="peStore.selectedRoleType">
        <sortable-wrapper
            :list="peStore.selectedRoleType.fields.selected"
            :item-key="(item) => item.field"
            :options="sortableOptions"
            @end="resequenceFields"
        >
            <template #item="item">
                <!-- <Transition :key="peStore.selectedRoleType" @enter="slideDownOnEnter" @leave="slideUpOnLeave"> -->
                <pe-fields-selected-item :field="item.element" :dragging="false" />
                <!-- </Transition> -->
            </template>
        </sortable-wrapper>

        {{ peStore.selectedRoleType.fields.selected }}

        <div v-if="peStore.selectedRoleType.fields.selected.length === 0" class="masonry-placeholder">
            You can drag fields into this space
        </div>
    </template>
</template>

<style>
.masonry-placeholder {
    display: block;
    height: 51px;
}
</style>
