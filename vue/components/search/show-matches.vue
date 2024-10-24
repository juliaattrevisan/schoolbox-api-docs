<script setup lang="ts">
/**
 * To highlight where the search query string matches within the provided text
 */

import { addMarks } from "@js/util/domUtils";

interface Props {
    tag: string; // the element to display
    text: string; // the text to show in the element
    query: string; // the list of words within the query
    allowPartialMatches?: boolean; // whether to allow partial matches, so can match part of a word
    isSearching?: boolean; // whether the search is currently active, if not we don't need to use v-html
    href?: string; // the href to navigate to
    class?: string; // the classes to apply to the elements
    title?: string; // the title to display
    disabled?: boolean; // whether the element is disabled
    for?: string; // the for attribute
    preventDefault?: boolean; // whether to prevent the default action
}

const props = withDefaults(defineProps<Props>(), {
    isSearching: true,
    href: undefined,
    class: undefined,
    title: undefined,
    disabled: undefined,
    for: undefined,
    preventDefault: false,
});

/**
 * the search string broken into words, to search for each word
 * @returns {string[]} the search string broken into single words
 */
const queryWordList = window.Vue.computed(() => {
    const queryTrimmed = props.query.trim(); // remove any front or back white space
    const regularExpression = /[^a-zA-Z0-9]+/; // match any non-alphanumeric characters
    const commaSeparated = queryTrimmed.split(regularExpression) ?? []; // split the string into a list of words
    return commaSeparated.filter((word) => word.length > 0); // and ensure each contains a character
});

const emit = defineEmits<{
    click: [];
}>();

/**
 * When the element is clicked, emit the click event
 * @param event the event that triggered the click
 */
function onClicked(event: Event): void {
    // to prevent the default action, which is to navigate to the href if the element is an anchor
    if (props.preventDefault) {
        event.preventDefault();
    }
    emit("click");
}
</script>

<template>
    <!-- eslint-disable vue/no-v-html | addMarks scrubs the props.text -->
    <!-- eslint-disable vue/no-v-text-v-html-on-component | props.tag is passed through  -->
    <component
        :is="props.tag"
        v-if="props.isSearching"
        :href="props.href ? props.href : undefined"
        :class="props.class ? props.class : false"
        :title="props.title ? props.title : undefined"
        :disabled="props.disabled ? props.disabled : undefined"
        :for="props.for ? props.for : undefined"
        @click="onClicked"
        v-html="addMarks(props.text, queryWordList, props.allowPartialMatches)"
    ></component>
    <!-- eslint-enable vue/no-v-html -->
    <!-- eslint-enable vue/no-v-text-v-html-on-component -->
    <component
        :is="props.tag"
        v-else
        :href="props.href ? props.href : undefined"
        :class="props.class ? props.class : false"
        :title="props.title ? props.title : undefined"
        :disabled="props.disabled ? props.disabled : undefined"
        :for="props.for ? props.for : undefined"
        @click="onClicked"
        >{{ props.text }}</component
    >
</template>

<style scoped></style>
