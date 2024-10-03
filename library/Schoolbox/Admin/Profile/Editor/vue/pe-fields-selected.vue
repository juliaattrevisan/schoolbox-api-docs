<script setup lang="ts">
/**
 * The selected fields for the current role type
 */

import type Sortable from "sortablejs";
import SortableWrapper from "@schoolboxVue/components/sortable-wrapper.vue";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import PeFieldsSelectedItem from "./pe-fields-selected-item.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";
import { useProfileEditorFieldsStore } from "./store/profileEditorFieldsStore";

const peStore = useProfileEditorStore();
const peFieldsStore = useProfileEditorFieldsStore();

const sortableOptions = window.Vue.computed(() => ({
    // Link the selected fields sortable to the available fields sortable so that we can drag between them
    group: {
        name: `fields-${peStore.selectedRoleType ? peStore.selectedRoleType.roleType : "-"}`,
        pull: false, // can't drag out of the selected fields
        put: true, // can drag into the selected fields
    },
    // We use this to force the html5 drag and drop logic to be ignored and use the fallback so we can style it
    forceFallback: true,
    // The class to apply to the clone when dragging
    fallbackClass: "dragged-item",
    // How long the animation should take when items shift
    animation: 200,
    // The handle to use to drag the field around
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
async function onAddField(event: Sortable.SortableEvent): Promise<void> {
    const field = event.item.getAttribute("data-pe-field");
    if (field === null) throw new Error("dragged item is missing a data-pe-field attribute");

    // Send the actual item (controlled by vue) back into the palette to keep its reactivity
    event.clone.replaceWith(event.item);

    await peFieldsStore.enableField(field, event.newIndex);
}
</script>

<template>
    <div v-if="peStore.selectedRoleType" class="pe-fields-selected">
        <sortable-wrapper
            :list="peStore.selectedRoleType.fields.selected"
            :item-key="(item) => item.field"
            :options="sortableOptions"
            :transition-key="peStore.selectedRoleType.roleType"
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
