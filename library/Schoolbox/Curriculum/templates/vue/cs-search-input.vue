<script setup>

/**
 * To search for codes or select the browse button to open the modal
 */

import { ref } from 'vue'; 
import { useModalStore } from './store/csModalStore'; 
import { useSearchResultsStore } from './store/csSearchResultsStore'; 


const searchResultsStore = useSearchResultsStore();
const modalStore = useModalStore();
const isLoading = ref(false);
const hasLoadError = ref(false);
const isClassSelectorActive = ref(true);
const inputPlaceholder = ref('Search for curriculum codes or keywords...');

/**
 * to open the modal
 */
function onBrowseSelected () {
    modalStore.open();
}

</script>

<template>
    <div class="input-group">
        <input
            v-if="isClassSelectorActive"
            v-model="searchResultsStore.query"
            ref="queryInputEl"
            type="text"
            class="search-input"
            :readonly="isLoading || hasLoadError"
            :placeholder="inputPlaceholder"
            @input="$event.target.composing = false"
        />
        <button class="button icon-curriculum" title="Browse and select from the Curriculum" @click.prevent="onBrowseSelected">Browse</button>
        <input name="curriculumCodes[]" type="hidden" tabindex="-1" value="" />
    </div>
</template>

<style scoped>

</style>