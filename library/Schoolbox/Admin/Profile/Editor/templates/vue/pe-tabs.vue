<script setup lang="ts">
/**
 * The tabs available on the profile page for this role type
 */

import type Sortable from "sortablejs";
import SortableWrapper from "@schoolboxVue/components/sortable-wrapper.vue";
import PeTabsTab from "./pe-tabs-tab.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();

const sortableOptions = window.Vue.computed<Sortable.Options>(() => ({
    // We use this to force the html5 drag and drop logic to be ignored and use the fallback so we can style it
    forceFallback: true,
    // The class to apply to the clone when dragging
    fallbackClass: "dragged-item",
    // How long the animation should take when items shift
    animation: 200,
    // You can sort both horizontally and vertically
    direction: "horizontal",
    // The handle to use to drag the field around
    handle: "[data-pe-tabs-tab-drag-handle]",
}));
</script>

<template>
    <div v-if="peStore.selectedRoleType" class="small-12 island">
        <h3 class="subheader">Tabs</h3>
        <div>
            <sortable-wrapper
                :list="peStore.selectedRoleType.tabs"
                :item-key="(item) => item.id"
                :options="sortableOptions"
                class="l-grid l-grid--6-col l-gap-xs"
            >
                <template #item="item">
                    <pe-tabs-tab :tab="item.element" :dragging="item.isDragging" />
                </template>
            </sortable-wrapper>
        </div>
    </div>
</template>

<style scoped></style>
