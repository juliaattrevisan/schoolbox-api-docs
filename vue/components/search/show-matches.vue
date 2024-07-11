<script setup lang="ts">
/**
 * To highlight where the search query string matches within the provided text
 */

import { addMarks } from "@js/util/domUtils";

interface Props {
    tag: string; // the result to display
    text: string; // the text to show in the element
    query: string; // the list of words within the query
    href?: string | undefined; //
}

const props = defineProps<Props>();

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
        :href="props.href ? props.href : undefined"
        @click.prevent="emit('click')"
        v-html="addMarks(props.text, queryWordList)"
    ></component>
    <!-- eslint-enable vue/no-v-html -->
    <!-- eslint-enable vue/no-v-text-v-html-on-component -->
</template>

<style scoped></style>
