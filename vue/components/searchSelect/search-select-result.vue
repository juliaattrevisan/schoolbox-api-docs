<script setup lang="ts">
/**
 * A single search result that can be selected / unselected with a checkbox status
 */

import { addMarks } from "@js/util/domUtils";
import type { Result } from "./types/Result";

interface Props {
    query: string; // the search query
    selected: Result[]; // the selected results
    results: Result[]; // the results returned from the search
    result: Result; // the result to display
}

const props = defineProps<Props>();

const emit = defineEmits<{
    select: [result: Result];
}>();

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
function onSelected() {
    emit("select", props.result);
}
</script>

<template>
    <button class="search-result" :title="result.name" @click.prevent="onSelected">
        <div class="checklist">
            <input :id="`checkbox-${result.id}`" type="checkbox" :checked="isSelected" />
            <!-- eslint-disable-next-line vue/no-v-html -->
            <label :for="`checkbox-${result.id}`" v-html="addMarks(props.result.name, [props.query])"></label>
        </div>
    </button>
</template>

<style lang="scss" scoped>
.search-result {
    background: var(--foreground-colour);
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
}

input[type="checkbox"]:not(.plain) + label {
    margin: 0;
}
</style>
