<script setup lang="ts">
/**
 * To display an error if the search fails
 */

import { useSearchStore } from "../store/spSearchStore";

const searchStore = useSearchStore();

/**
 * Try to search again
 */
async function onTryAgainSelected(): Promise<void> {
    await searchStore.search(true);
}
</script>

<template>
    <div class="alert-box alert" data-test="sp-results-error">
        <template v-if="searchStore.searchError">
            {{ searchStore.searchError.title }}
        </template>
        <template v-else>
            Sorry, there was an unknown error retrieving your search results. Please
            <button title="try to search again" @click="onTryAgainSelected">try again</button> and contact your support
            if the problem persists.
        </template>
    </div>
</template>

<style scoped></style>
