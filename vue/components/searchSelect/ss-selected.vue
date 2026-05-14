<script setup lang="ts">
/**
 * To list all the currently selected items
 */

import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import SsSelectedItem from "./ss-selected-item.vue";
import { ON_FILTER_CHANGE } from "./event/SbxSearchSelectEvent";

interface Props {
    selected: SearchSelectItem[]; // the selected items
    formValueName: string; // the name of the form value
    isSingleSelect: boolean; // whether only one result can be selected
    canSelectItem: boolean; // whether the user can select an item (to trigger the select emit)
    isReadOnly?: boolean; // whether can remove items
    forFilterPanel?: boolean; // whether this search select is being used within a filter panel, which requires some specific behaviour around dispatching change events on selection change
}
const props = defineProps<Props>();

const emit = defineEmits<{
    select: [item: SearchSelectItem];
    remove: [items: SearchSelectItem[]];
}>();

const inputEl = window.Vue.useTemplateRef<HTMLInputElement>("inputEl");
const selectEl = window.Vue.useTemplateRef<HTMLSelectElement>("selectEl");

/**
 * Add a comma separated string of the ids of the selected ids
 * for use in forms
 * @returns - a comma separated string of the ids
 */
const ids = window.Vue.computed(() => {
    return props.selected.map((item) => item.id).join(",");
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

/**
 * to keep the filter panel / search select in sync when changes are made to either
 */
if (props.forFilterPanel) {
    /**
     * To manually dispatch a <select> change event when there's been a change in the selection
     * As this is being handled by Vue rather than user input, the change event won't fire otherwise
     * except when the <select> is first displayed in the DOM.
     * We listen for this in library/Schoolbox/Filter/templates/panel.twig
     */
    window.Vue.watch(ids, async () => {
        await window.Vue.nextTick(); // nextTick to ensure the dom has updated so we can access with the latest changes
        const event = new Event("change");
        selectEl.value?.dispatchEvent(event);
        inputEl.value?.dispatchEvent(event);
    });

    /**
     * to listen for any clears of the selected codes from outside the Vue app
     */
    window.Vue.onMounted(() => {
        document.addEventListener(ON_FILTER_CHANGE, (event) => {
            if (selectEl.value?.getAttribute("id") === event.detail.elementId) {
                // just in case there are multiple on the page
                const currentSelection = props.selected;
                const newSelection = JSON.parse(event.detail.selection) as SearchSelectItem[];
                // compare the newSelection with the currentSelection, removing any from the current selection that aren't in the new selection
                const itemsToRemove = currentSelection.filter(
                    (item) => !newSelection.some((newItem: SearchSelectItem) => newItem.id === item.id),
                );
                if (itemsToRemove.length > 0) {
                    emit("remove", itemsToRemove);
                }
            }
        });
    });
}
</script>

<template>
    <div v-if="props.selected.length > 0" class="ss-selected l-flex-row l-gap-s l-pad-s" data-test="ss-selected">
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
    <input
        :id="props.formValueName"
        ref="inputEl"
        :name="props.formValueName"
        type="hidden"
        tabindex="-1"
        :value="ids"
    />
    <select
        v-if="$props.forFilterPanel"
        :id="`${props.formValueName}[]`"
        ref="selectEl"
        :name="`${props.formValueName}[]`"
        :data-selected-original="JSON.stringify(props.selected)"
        :data-selected-default="JSON.stringify([])"
        tabindex="-1"
        multiple
        data-search-select
        hidden
    >
        <option
            v-for="item in props.selected"
            :key="item.id"
            :value="item.id"
            :data-selected-object="item.name"
            selected
        >
            {{ item.name }}
        </option>
    </select>
</template>
