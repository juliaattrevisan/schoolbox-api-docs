<script setup lang="ts">
/**
 * To list all the currently selected Curriculum codes
 */

import type { Result } from "./types/Result";
import SearchSelectSelectedItem from "./search-select-selected-item.vue";

interface Props {
    selectedItems: Result[]; // the selected results
    formValueName: string; // the name of the form value
    isSingleSelect: boolean; // whether only one result can be selected
}
const props = defineProps<Props>();

const emit = defineEmits<{
    remove: [result: Result];
}>();

/**
 * Add a comma separated string of the ids of the selected codes
 * for use in forms
 * @returns {string}
 */
const ids = window.Vue.computed(() => {
    return props.selectedItems.join(",");
});

/**
 * To remove a selected search result
 * @param result - the result to remove
 */
function onRemoveItem(result: Result): void {
    emit("remove", result);
}
</script>

<template>
    <div v-if="props.selectedItems.length > 0" class="selected-container l-flex-row l-gap-s l-pad-s">
        <search-select-selected-item
            v-for="result in props.selectedItems"
            :key="result.id"
            :result="result"
            :is-single-select="props.isSingleSelect"
            @remove="onRemoveItem"
        />
    </div>
    <input :id="props.formValueName" :name="props.formValueName" type="hidden" tabindex="-1" :value="ids" />
</template>

<style lang="scss" scoped>
.selected-container {
    border: 1px solid var(--form-border-color);
    margin-bottom: -1px; //to butt up against the search input
    max-height: 133px;
    min-height: 50px;
    overflow: auto;
}
</style>
