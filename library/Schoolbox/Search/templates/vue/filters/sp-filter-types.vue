<script setup lang="ts">
/**
 * To filter the results by types
 */

import { useFilterTypesStore } from "./store/spFilterTypesStore";
import SpFilterTypesType from "./sp-filter-types-type.vue";

const filterTypesStore = useFilterTypesStore();

/**
 * To show/hide all the sub types when the all sub types button is clicked
 */
function onExpandAllSelected() {
    filterTypesStore.isExpanded = !filterTypesStore.isExpanded;
}

/**
 * To show/hide the sub types container
 * @returns {string} - the height of the sub types container
 */
const expandButtonText = window.Vue.computed(() => {
    return filterTypesStore.isExpanded ? "Collapse all" : "Expand all";
});
</script>

<template>
    <div>
        <label>Types</label>
        <div class="checklist">
            <button
                :class="`button full-width ${filterTypesStore.isExpanded ? 'icon-down-arrow' : 'icon-up-arrow'}`"
                @click.prevent="onExpandAllSelected"
            >
                {{ expandButtonText }}
            </button>
            <ul class="option-list t-no-list-style l-flex-col l-gap-s">
                <sp-filter-types-type
                    v-for="typeItem in filterTypesStore.allTypes"
                    :key="typeItem.value"
                    :type="typeItem"
                />
            </ul>
        </div>
    </div>
</template>

<style scoped>
.checklist {
    border: 1px solid var(--form-border-color);
}
.option-list {
    padding: var(--whitespace-s);
}
</style>
