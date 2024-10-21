<script setup lang="ts">
/**
 * A single search result that can be selected / unselected with a checkbox/radio status
 */

import { toRef } from "vue";
import ShowMatches from "@schoolboxVue/components/search/show-matches.vue";
import { useActiveIndex } from "./composables/useActiveIndex";
import type { SearchSelectItem } from "./types/searchSelectTypes";

interface Props {
    query: string; // the search query
    selectedItems: SearchSelectItem[]; // the selected items
    result: SearchSelectItem; // the result to display
    isSingleSelect: boolean; // whether only one result can be selected
    index: number; // the index of the result
    activeIndex: number; // the active index, to determine if this result is active
}
const props = defineProps<Props>();

const emit = defineEmits<{
    add: [items: SearchSelectItem[]];
    remove: [items: SearchSelectItem[]];
    hover: [index: number];
}>();

/**
 * To determine whether the radio/checkbox is currently selected
 * based on whether it's in the selected list
 * @returns - whether the result is selected
 */
const isChecked = window.Vue.computed(() => {
    return props.selectedItems.some((result) => result.id === props.result.id);
});

/**
 * To toggle the selected state of the result
 * @param event - the click event
 */
function onClick(event?: MouseEvent | undefined): void {
    // ignore input label clicks, which get picked up due to the click listener on the parent div
    if (event && event.target && (event.target as HTMLElement).nodeName === "LABEL") {
        return;
    }
    if (isChecked.value) {
        emit("remove", [props.result]);
    } else {
        emit("add", [props.result]);
    }
}

/**
 * To potentially shift the active index to this result
 */
function onMouseover(): void {
    emit("hover", props.index);
}

/**
 * To unset the active index
 */
function onMouseout(): void {
    emit("hover", -1);
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
        :data-test="`ss-results-list-item-container${props.result.id}`"
        @click="onClick"
        @mouseover="onMouseover"
        @mouseout="onMouseout"
    >
        <div class="checklist">
            <input
                v-if="props.isSingleSelect"
                :id="`search-select-result-input-${result.id}`"
                type="radio"
                :checked="isChecked"
                :data-test="`ss-results-list-item-radio-${props.result.id}`"
                @click.stop="onClick"
            />
            <input
                v-else
                :id="`search-select-result-input-${result.id}`"
                type="checkbox"
                :checked="isChecked"
                :data-test="`ss-results-list-item-checkbox-${props.result.id}`"
                @click.stop="onClick"
            />
            <show-matches
                tag="label"
                :for="`search-select-result-input-${result.id}`"
                :text="props.result.name"
                :query="props.query"
                class="f-no-margins"
            />
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
