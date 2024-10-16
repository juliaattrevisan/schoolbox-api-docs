<script setup lang="ts">
/**
 * A single search result that can be selected / unselected with a checkbox status
 */

import { toRef } from "vue";
import ShowMatches from "@schoolboxVue/components/search/show-matches.vue";
import { useActiveIndex } from "./composables/useActiveIndex";
import type { Result } from "./types/Result";

interface Props {
    query: string; // the search query
    selectedItems: Result[]; // the selected results
    result: Result; // the result to display
    isSingleSelect: boolean; // whether only one result can be selected
    index: number; // the index of the result
    activeIndex: number; // the active index, to determine if this result is active
}
const props = defineProps<Props>();

const emit = defineEmits<{
    select: [result: Result];
    deselect: [result: Result];
}>();

/**
 * To determine whether the checkbox is currently selected
 * based on whether it's in the selected list
 * @returns {boolean}
 */
const isChecked = window.Vue.computed(() => {
    return props.selectedItems.some((result) => result.id === props.result.id);
});

/**
 * To toggle the selected state of the result
 */
function onSelected(): void {
    console.warn("onSelected");
    if (isChecked.value) {
        emit("deselect", props.result);
    } else {
        emit("select", props.result);
    }
}

const resultEl = window.Vue.ref<HTMLElement | null>(null);

/**
 * To allow keyboard navigation of the search results
 */
const { isActive } = useActiveIndex(
    resultEl,
    props.index,
    toRef(() => props.activeIndex),
    onSelected,
);
</script>

<template>
    <div
        ref="resultEl"
        :class="`search-result ${isActive ? 'search-result--active' : ''} f-no-margins`"
        @click="onSelected"
    >
        <show-matches v-if="props.isSingleSelect" tag="span" :text="props.result.name" :query="props.query" />
        <div v-else class="checklist">
            <input :id="`checkbox-${result.id}`" type="checkbox" :checked="isChecked" @click.stop="onSelected" />
            <show-matches
                tag="label"
                :for="`checkbox-${result.id}`"
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
    &:hover,
    &:focus {
        background: var(--content-ui-hover);
    }
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
