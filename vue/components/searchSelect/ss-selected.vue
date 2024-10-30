<script setup lang="ts">
/**
 * To list all the currently selected items
 */

import type { SearchSelectItem } from "../../composables/search/types/SearchSelectItem";
import SsSelectedItem from "./ss-selected-item.vue";

interface Props {
    selected: SearchSelectItem[]; // the selected items
    formValueName: string; // the name of the form value
    isSingleSelect: boolean; // whether only one result can be selected
    canSelectItem: boolean; // whether the user can select an item (to trigger the select emit)
    isReadOnly?: boolean; // whether can remove items
}
const props = defineProps<Props>();

const emit = defineEmits<{
    select: [item: SearchSelectItem];
    remove: [items: SearchSelectItem[]];
}>();

/**
 * Add a comma separated string of the ids of the selected ids
 * for use in forms
 * @returns - a comma separated string of the ids
 */
const ids = window.Vue.computed(() => {
    return props.selected.join(",");
});

/**
 * To select the name of the selected item
 * @param item - the item to select
 */
function onSelectItem(item: SearchSelectItem): void {
    emit("select", item);
}

/**
 * To remove a selected item
 * @param item - the item to remove
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
            :can-select-item="props.canSelectItem"
            :is-read-only="props.isReadOnly"
            @select="onSelectItem"
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
