<script setup lang="ts">
/**
 * A component showing all available fields that can be added to the role type
 */

import type Sortable from "sortablejs";
import SortableWrapper from "@schoolboxVue/components/sortable-wrapper.vue";
import PeFieldsAvailableItem from "./pe-fields-available-item.vue";
import PeFieldsSelectedItem from "./pe-fields-selected-item.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();

const sortableOptions = window.Vue.computed<Sortable.Options>(() => ({
    group: {
        name: `fields-${peStore.selectedRoleType ? peStore.selectedRoleType.roleType : "-"}`,
        pull: "clone",
        put: false,
    },
    forceFallback: true,
    fallbackClass: "dragged-item",
    filter: "[data-pe-prevent-drag]",
    animation: 200,
    sort: false,
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
                                <pe-fields-selected-item
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
.pe-fields-palette {
    background-color: #ffffff;
}

/* Hide the selected preview while we are still in the palette area */
.pe-fields-palette .pe-dragged-selected-item {
    display: none !important;
}
</style>
