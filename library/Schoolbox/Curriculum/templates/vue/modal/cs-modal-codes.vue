<script setup>

/**
 * The filtered results from using the <selects> or search <input>
 * From here curriclum codes can be selected 
 */

import { ref, computed, watch } from 'vue';
import { useCodesStore } from '../store/csCodesStore';
import { useModalActiveCodesStore } from '../store/csModalActiveCodesStore';
import CsModalCode from './cs-modal-code.vue'

const codesStore = useCodesStore();
const modalActiveCodesStore = useModalActiveCodesStore();
const depth = ref(0); //to increment each time we go down the node tree to visually indicate children
const codesContainerEl = ref(null); //to scroll back to top on any selection change


/**
 * To list the parent node titles, separated by a command and or for the last
 *
 * @returns { String }
 */
const parentNodeTitles = computed(() => {
    const parentTitlesList = codesStore.codes.map((code) => code.name); //just get the titles
    return parentTitlesList.join(', ').replace(/, ([^,]*)$/, ' or $1'); //or on the last 
});

/**
 * To further clarify where the current search term hasn't found any code matches
 *
 * @returns { String }
 */
const currentSelectedNodeTitle = computed(() => modalActiveCodesStore.codes[0].name);

/**
 * Clear the current search query
 */
function onClearSearchSelected () {
    modalActiveCodesStore.clearSearch();
}

/**
 * to scroll back to top on any selection change
 */
watch(() => modalActiveCodesStore.codesWithSearch, () => {
    if (codesContainerEl.value) { //may not always exists in the dom
        codesContainerEl.value.scrollTop = 0;
    }
});


</script>

<template>
    <div v-if="modalActiveCodesStore.codes.length === 0" class="empty-state">
        <i class="icon-curriculum"></i>
        <p>To start viewing Curriculum Codes, first select from either: {{ parentNodeTitles }}.</p>
    </div>
    <div v-else-if="modalActiveCodesStore.hasNoSearchResults" class="empty-state">
        <i class="icon-curriculum"></i>
        <p>No Curriculum Codes in: {{ currentSelectedNodeTitle }} match your current search.</p>
        <button @click.prevent="onClearSearchSelected" @keyup.enter="onClearSearchSelected" title="Clear the current search">Clear search</button>
    </div>
    <ul v-else ref="codesContainerEl">
        <cs-modal-code
            v-for="code in modalActiveCodesStore.codesWithSearch"
            :key="code.id"
            :code="code"
            :depth="depth"
        />
    </ul>
</template>

<style lang="scss" scoped>

$height: 500px;

ul {
    list-style: none;
    margin: 0 0 1rem 0;
    max-height: $height; /*restrict the size of the modal, it'll scroll when larger */
    min-height: $height;
    overflow-y: auto;
}

.empty-state {
    align-items: center;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: $height;
    p {
        max-width: 50%;
    }
    button {
        background: none;
        font-size: 0.875rem;
    }
}
    
</style>