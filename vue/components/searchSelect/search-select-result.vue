<script setup lang="ts">
/**
 * A single search result that can be selected / unselected with a checkbox status
 */

import ShowMatches from "@schoolboxVue/components/search/show-matches.vue";
import type { Result } from "./types/Result";

interface Props {
    query: string; // the search query
    selectedItems: Result[]; // the selected results
    result: Result; // the result to display
    isSingleSelect: boolean; // whether only one result can be selected
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
const isSelected = window.Vue.computed(() => {
    return props.selectedItems.some((result) => result.id === props.result.id);
});

/**
 * To toggle the selected state of the result
 */
function onSelected(): void {
    if (isSelected.value) {
        emit("deselect", props.result);
    } else {
        emit("select", props.result);
    }
}
</script>

<template>
    <div class="search-result f-no-margins" @click.prevent="onSelected">
        <show-matches v-if="props.isSingleSelect" tag="span" :text="props.result.name" :query="props.query" />
        <div v-else class="checklist">
            <input :id="`checkbox-${result.id}`" type="checkbox" :checked="isSelected" />
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
