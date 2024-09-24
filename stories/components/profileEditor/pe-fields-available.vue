<script setup lang="ts">
/**
 * A component showing all available fields that can be added to the role type
 */

import type Sortable from "sortablejs";
import SortableWrapper from "./sortable-wrapper.vue";
import PeFieldsAvailableItem from "./pe-fields-available-item.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();

const sortableOptions = window.Vue.computed<Sortable.Options>(() => ({
    group: {
        name: `fields-${peStore.selectedRoleType ? peStore.selectedRoleType.name : "-"}`,
        pull: "clone",
        put: false,
    },
    forceFallback: true,
    fallbackClass: "dragged-item",
    animation: 200,
    sort: false,
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
                        class="l-grid l-grid--12"
                    >
                        <template #item="item">
                            <pe-fields-available-item :field="item.element" :dragging="item.isDragging" />
                        </template>
                    </sortable-wrapper>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
.pe-fields-palette {
    background-color: white;
}
</style>
