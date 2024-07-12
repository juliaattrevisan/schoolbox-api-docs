<script setup lang="ts">
/**
 * To highlight where the search query string matches within the provided text
 */

import { addMarks } from "@js/util/domUtils";

interface Props {
    tag: string; // the element to display
    text: string; // the text to show in the element
    query: string; // the list of words within the query
    isSearching?: boolean; // whether the search is currently active, if not we don't need to use v-html
    href?: string | undefined; // the href to navigate to
    class?: string | undefined; // the classes to apply to the elements
    title?: string | undefined; // the title to display
    disabled?: boolean | undefined; // whether the element is disabled
    for?: string | undefined; // the for attribute
}

const props = withDefaults(defineProps<Props>(), {
    isSearching: true,
    href: undefined,
    class: undefined,
    title: undefined,
    disabled: undefined,
    for: undefined,
});

/**
 * the search string broken into words, to search for each word
 * @returns {string[]} the search string broken into single words
 */
const queryWordList = window.Vue.computed(() => {
    const queryTrimmed = props.query.trim(); // remove any front or back white space
    const regularExpression = /\s*(?:,|$)\s*/; // adapted from https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/split#removing_spaces_from_a_string
    const commaSeparated = queryTrimmed.split(regularExpression) ?? []; // split the string into a comma separated list
    return commaSeparated.filter((word) => word.length > 0); // and ensure each contains a character
});

const emit = defineEmits<{
    click: [];
}>();
</script>

<template>
    <!-- eslint-disable vue/no-v-html | addMarks scrubs the props.text -->
    <!-- eslint-disable vue/no-v-text-v-html-on-component | props.tag is passed through  -->
    <component
        :is="props.tag"
        v-if="props.isSearching"
        :href="props.href ? props.href : undefined"
        :class="props.class ? props.class : undefined"
        :title="props.title ? props.title : undefined"
        :disabled="props.disabled ? props.disabled : undefined"
        :for="props.for ? props.for : undefined"
        @click.prevent="emit('click')"
        v-html="addMarks(props.text, queryWordList)"
    ></component>
    <!-- eslint-enable vue/no-v-html -->
    <!-- eslint-enable vue/no-v-text-v-html-on-component -->
    <component
        :is="props.tag"
        v-else
        :href="props.href ? props.href : undefined"
        :class="props.class ? props.class : undefined"
        :title="props.title ? props.title : undefined"
        :disabled="props.disabled ? props.disabled : undefined"
        :for="props.for ? props.for : undefined"
        @click.prevent="emit('click')"
        >{{ props.text }}</component
    >
</template>

<style scoped></style>
