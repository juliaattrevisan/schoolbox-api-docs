<script setup lang="ts">
/**
 * Toggle the selection of currently listed search results so can select
 * multiple search results at once
 */

import { toRef } from "vue";
import { useActiveIndex } from "./composables/useActiveIndex";
import type { Result } from "./types/Result";

interface Props {
    results: Result[]; // the results returned from the search
    selectedItems: Result[]; // the selected items
    index: number; // the index of the result
    activeIndex: number; // the active index, to determine if this result is active
}
const props = defineProps<Props>();

const emit = defineEmits<{
    add: [results: Result[]];
    remove: [results: Result[]];
    hover: [index: number];
}>();

/**
 * To determine the behaviour of the 'Select all' button, whether to
 * add all results or remove all results
 * @returns - whether all results are selected
 */
const isAllResultsSelected = window.Vue.computed(() => {
    if (props.selectedItems.length > 0) {
        const alreadySelected = props.results.filter((resultItem) => {
            return props.selectedItems.some((node) => resultItem.id === node.id);
        });
        return alreadySelected.length === props.results.length;
    }
    return false;
});

/**
 * To add the 'indeterminate' class to the select all checkbox to show some
 * in the search results are already selected
 * @returns - whether some results are selected
 */
const isSomeResultsSelected = window.Vue.computed(() => {
    if (props.selectedItems.length > 0) {
        const alreadySelected = props.results.filter((resultItem) => {
            return props.selectedItems.some((node) => resultItem.id === node.id);
        });
        return alreadySelected.length >= 1 && alreadySelected.length !== props.results.length;
    }
    return false;
});

/**
 * To determine the check display of the checkbox
 * @returns - whether it's checked
 */
const isChecked = window.Vue.computed(() => isAllResultsSelected.value);

/**
 * toggle all depending on current check status
 */
function onClick(): void {
    if (isChecked.value) {
        emit("remove", props.results);
    } else {
        emit("add", props.results);
    }
}

/**
 * To toggle the selected state of the result
 */
function onMouseover(): void {
    emit("hover", props.index);
}

const resultEl = window.Vue.ref<HTMLElement | null>(null);

/**
 * To allow keyboard navigation of the search results
 */
const { isActive } = useActiveIndex(
    resultEl,
    props.index,
    toRef(() => props.activeIndex),
    onClick,
);
</script>

<template>
    <div
        ref="resultEl"
        :class="`search-result ${isActive ? 'search-result--active' : ''} f-no-margins`"
        @click="onClick"
        @mouseover="onMouseover"
    >
        <div class="checklist">
            <input
                id="checkbox-select-all"
                type="checkbox"
                :checked="isChecked"
                :class="{ indeterminate: isSomeResultsSelected }"
                @click.stop="onClick"
            />
            <label for="checkbox-select-all">Select All</label>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.search-result {
    background: var(--color-foreground-primary);
    border-bottom: 1px solid var(--body-background);
    border-left: 1px solid var(--form-border-color);
    border-right: 1px solid var(--form-border-color);
    display: flex;
    font-size: 0.825rem;
    padding: var(--spacing-s);
    transition: background 0.2s;
    width: 100%;
    &:last-of-type {
        border-bottom: none;
    }
    &--active {
        background: var(--content-ui-hover);
    }
}

input[type="checkbox"]:not(.plain) + label {
    margin: 0;
}
</style>
