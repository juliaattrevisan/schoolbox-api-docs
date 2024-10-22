<script setup lang="ts">
/**
 * To list all the currently selected Curriculum codes
 */

import type { SearchSelectItem } from "./types/searchSelectTypes";
import SsSelectedItem from "./ss-selected-item.vue";

interface Props {
    selected: SearchSelectItem[]; // the selected results
    formValueName: string; // the name of the form value
    isSingleSelect: boolean; // whether only one result can be selected
}
const props = defineProps<Props>();

const emit = defineEmits<{
    remove: [items: SearchSelectItem[]];
}>();

/**
 * Add a comma separated string of the ids of the selected codes
 * for use in forms
 * @returns - a comma separated string of the ids
 */
const ids = window.Vue.computed(() => {
    return props.selected.join(",");
});

/**
 * To remove a selected search result
 * @param item - the result to remove
 */
function onRemoveItem(item: SearchSelectItem): void {
    emit("remove", [item]);
}
</script>

<template>
    <div v-if="props.selected.length > 0" class="selected l-flex-row l-gap-s l-pad-s">
        <ss-selected-item
            v-for="item in props.selected"
            :key="item.id"
            :item="item"
            :is-single-select="props.isSingleSelect"
            @remove="onRemoveItem"
        />
    </div>
    <input :id="props.formValueName" :name="props.formValueName" type="hidden" tabindex="-1" :value="ids" />
</template>

<style lang="scss" scoped>
.selected {
    border: 1px solid var(--form-border-color);
    margin-bottom: -1px; //to butt up against the search input
    max-height: 133px;
    min-height: 50px;
    overflow: auto;
}
</style>
