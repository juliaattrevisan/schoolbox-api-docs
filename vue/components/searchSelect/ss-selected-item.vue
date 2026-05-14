<script setup lang="ts">
/**
 * Displays a single selected item that can be removed
 */

import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";

interface Props {
    item: SearchSelectItem; // the selected item
    isSingleSelect: boolean; // whether only one item can be selected
    canSelectItem: boolean; // whether the user can select an item (to trigger the select emit)
    isReadOnly?: boolean; // whether can remove items
}
const props = defineProps<Props>();

const emit = defineEmits<{
    select: [item: SearchSelectItem];
    remove: [item: SearchSelectItem];
}>();

/**
 * Prevent rapid fire while loading the selected node
 * @returns - whether the button is disabled
 */
const isDisabled = window.Vue.computed(() => false);

/**
 * To trigger an action of the search result, such as opening a modal
 * containing further info on the selected search result
 */
function onNameSelected(): void {
    emit("select", props.item);
}

/**
 * To remove from the selected list
 */
function onRemoveSelected(): void {
    emit("remove", props.item);
}
</script>

<template>
    <div
        :class="`ss-selected-item l-flex l-flex--align-center l-pad-s l-gap-s ${
            props.isSingleSelect ? 'l-flex__item--1' : ''
        }`"
        :data-test="`ss-selected-item-container-${props.item.id}`"
    >
        <button
            v-if="props.canSelectItem"
            type="button"
            :class="`ss-selected-item__name-button ${props.isSingleSelect ? 'l-flex__item--1' : ''}`"
            :title="`View ${props.item.name}`"
            :aria-label="`View ${props.item.name}`"
            :disabled="isDisabled"
            :data-test="`ss-selected-item-select-${props.item.id}`"
            @click.prevent="onNameSelected"
        >
            {{ props.item.name }}
        </button>
        <span v-else class="ss-selected-item__name-span">{{ props.item.name }}</span>
        <button
            v-if="!props.isReadOnly"
            type="button"
            class="ss-selected-item__remove-button icon-close"
            :title="`Remove ${props.item.name}`"
            :aria-label="`Remove ${props.item.name}`"
            :data-test="`ss-selected-list-item-remove-${props.item.id}`"
            @click.prevent="onRemoveSelected"
        ></button>
    </div>
</template>
