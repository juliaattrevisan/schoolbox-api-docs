<script setup lang="ts">
/**
 * A single search result that be selected / unselected with a checkbox status
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
            <label :for="`checkbox-${result.id}`"></label>
        </div>
        <div>
            <!-- eslint-disable-next-line vue/no-v-html -->
            <span v-html="addMarks(props.result.name, [props.query])"></span>
        </div>
    </button>
</template>

<style lang="scss" scoped>
.search-result {
    background: white;
    display: flex;
    font-size: 0.825rem;
    margin: 0;
    padding: 0.5rem 0.75rem;
    text-align: left;
    transition: background 0.2s;
    width: 100%;
    &:hover {
        background: var(--content-ui-hover);
    }
}

.checklist {
    flex: 2rem 0 0; //ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it
    margin: 0;
}

input[type="checkbox"]:not(.plain) + label {
    margin: 0;
}

aside {
    font-size: 0.725rem;
    font-style: italic;
}
</style>
