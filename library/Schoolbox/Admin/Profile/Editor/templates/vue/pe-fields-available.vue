<script setup lang="ts">
/**
 * A component showing all available fields that can be added to the role type
 */

import type Sortable from "sortablejs";
import SortableWrapper from "@schoolboxVue/components/sortable-wrapper.vue";
import PeFieldsAvailableItem from "./pe-fields-available-item.vue";
import PeFieldsActiveItem from "./pe-fields-active-item.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();

const sortableOptions = window.Vue.computed<Sortable.Options>(() => ({
    // Link the available fields sortable to the selected fields sortable so that we can drag between them
    group: {
        name: `fields-${peStore.selectedRoleType ? peStore.selectedRoleType.roleType : "-"}`,
        pull: "clone", // dragging out of the available fields will create a clone
        put: false, // can't drag into the available fields
    },
    // We use this to force the html5 drag and drop logic to be ignored and use the fallback so we can style it
    forceFallback: true,
    // The class to apply to the clone when dragging
    fallbackClass: "dragged-item",
    // Prevent dragging on fields that are already selected
    filter: "[data-pe-prevent-drag]",
    // How long the animation should take when items shift
    animation: 200,
    // Prevent sorting inside the available fields list
    sort: false,
    // Removes the clone element when it is not showing, rather than just hiding it.
    removeCloneOnHide: true,
}));
</script>

<template>
    <div v-if="peStore.selectedRoleType !== null" class="component l-mar-m--bottom">
        <div class="component-titlebar">
            <div class="actions-small-2">
                <div class="list-item">
                    <h2 title="Fields">Fields</h2>
                </div>
            </div>
        </div>
        <div class="pe-fields-palette">
            <div class="small-12">
                <section class="content">
                    <sortable-wrapper
                        :list="peStore.selectedRoleType.fields.available"
                        :item-key="(item) => item.field"
                        :options="sortableOptions"
                        class="l-grid l-grid--4-col"
                    >
                        <template #item="item">
                            <div :data-pe-field="item.element.field">
                                <pe-fields-available-item :field="item.element" class="pe-fields-available-item" />
                                <pe-fields-active-item
                                    v-if="item.isDragging"
                                    :field="item.element"
                                    :dragging="true"
                                    class="pe-dragged-selected-item"
                                />
                            </div>
                        </template>
                    </sortable-wrapper>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Hide the selected preview while we are still in the palette area */
.pe-fields-palette .pe-dragged-selected-item {
    display: none !important;
}
</style>
