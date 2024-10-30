<script setup lang="ts">
/**
 * Displays the list of courses in a modal.
 */

import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";

interface Props {
    isLoading: boolean; // the label text
    hasError?: boolean; // Whether the input has an error
    errorMessage?: string; // The error message to display
    allowPartialMatches?: boolean; // whether to allow partial matches, so can match part of a word
}

const results = defineModel<SearchSelectItem[]>("results", { default: [] }); // to store search results
</script>

<template>
    <div>List</div>
</template>

<style scoped></style>
