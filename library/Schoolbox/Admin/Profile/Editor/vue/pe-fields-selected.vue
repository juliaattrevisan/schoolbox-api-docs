<script setup lang="ts">
/**
 * The selected fields for the current role type
 */

import type Sortable from "sortablejs";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import PeFieldsSelectedItem from "./pe-fields-selected-item.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";
import SortableWrapper from "./sortable-wrapper.vue";

const peStore = useProfileEditorStore();

const sortableOptions = window.Vue.computed(() => ({
    group: {
        name: `fields-${peStore.selectedRoleType ? peStore.selectedRoleType.name : "-"}`,
        pull: false,
        put: true,
    },
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

/**
 * When a field is added from the available fields
 * @param event The event that triggered the add
 */
function onAddField(event: Sortable.SortableEvent): void {
    const field = event.item.getAttribute("data-pe-field");
    if (field === null) throw new Error("dragged item is missing a data-pe-field attribute");

    peStore.addSelectedField(field, event.newIndex);

    // Send the actual item (controlled by vue) back into the palette to keep its reactivity
    event.clone.replaceWith(event.item);
}
</script>

<template>
    <div v-if="peStore.selectedRoleType" class="pe-fields-selected">
        <sortable-wrapper
            :list="peStore.selectedRoleType.fields.selected"
            :item-key="(item) => item.field"
            :options="sortableOptions"
            :transition-key="peStore.selectedRoleType.name"
            :transition-enter="slideDownOnEnter"
            :transition-leave="slideUpOnLeave"
            @end="resequenceFields"
            @add="onAddField"
        >
            <template #item="item">
                <pe-fields-selected-item :field="item.element" :dragging="item.isDragging" />
            </template>
        </sortable-wrapper>

        <div v-if="peStore.selectedRoleType.fields.selected.length === 0" class="masonry-placeholder">
            You can drag fields into this space
        </div>

        {{ peStore.selectedRoleType.fields.selected }}
    </div>
</template>

<style>
.masonry-placeholder {
    display: block;
    height: 51px;
}
.pe-fields-selected .pe-fields-available-item {
    display: none !important;
}
</style>
