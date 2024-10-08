<script setup lang="ts">
/**
 * A single search result that can be selected / unselected with a checkbox status
 */

import ShowMatches from "@schoolboxVue/components/search/show-matches.vue";
import { useSearchStore } from "./store/searchSelectStore";
import type { Result } from "./types/Result";

interface Props {
    selected: Result[]; // the selected results
    results: Result[]; // the results returned from the search
    result: Result; // the result to display
    isSingleSelect: boolean; // whether only one result can be selected
}

const props = defineProps<Props>();

const emit = defineEmits<{
    select: [result: Result];
}>();

const store = useSearchStore();

/**
 * To determine whether the checkbox is currently selected
 * based on whether it's in the selected list
 * @returns {boolean}
 */
const isSelected = window.Vue.computed(() => {
    return props.selected.some((result) => result.id === props.result.id);
});

/**
 * To toggle the selected state of the result
 */
function onSelected(): void {
    emit("select", props.result);
}
</script>

<template>
    <button class="search-result f-no-margins" :title="result.name" @click.prevent="onSelected">
        <show-matches v-if="props.isSingleSelect" tag="span" :text="props.result.name" :query="store.query" />
        <div v-else class="checklist">
            <input :id="`checkbox-${result.id}`" type="checkbox" :checked="isSelected" />
            <show-matches tag="label" :for="`checkbox-${result.id}`" :text="props.result.name" :query="store.query" />
        </div>
    </button>
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
    &:hover {
        background: var(--content-ui-hover);
    }
    &:last-of-type {
        border-bottom: none;
    }
}

input[type="checkbox"]:not(.plain) + label {
    margin: 0;
}
</style>
