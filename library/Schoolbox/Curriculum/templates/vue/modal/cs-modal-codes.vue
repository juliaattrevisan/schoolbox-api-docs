<script setup>

/**
 * The filtered results from using the <selects> or search <input>
 * From here curriclum codes can be selected
 */

import { ref, computed, watch } from 'vue';
import { useModalActiveCodesStore } from '../store/csModalActiveCodesStore';
import CsModalCode from './cs-modal-code.vue'


const modalActiveCodesStore = useModalActiveCodesStore();
const depth = ref(0); //to increment each time we go down the node tree to visually indicate children
const codesContainerEl = ref(null); //to scroll back to top on any selection change


/**
 * To further clarify where the current search term hasn't found any code matches
 *
 * @returns { String }
 */
const currentSelectedNodeTitle = computed(() => modalActiveCodesStore.codes[0].name);

/**
 * Clear the current search query
 */
// function onClearSearchSelected () {
//     modalActiveCodesStore.clearSearch();
// }

/**
 * to scroll back to top on any selection change, otherwise the
 * scroll may be at the bottom on change, which is a bit odd
 */
watch(() => modalActiveCodesStore.codes, () => {
    if (codesContainerEl.value) { //may not always exists in the dom
        codesContainerEl.value.scrollTop = 0;
    }
});


</script>

<template>
    <!-- <div v-if="modalActiveCodesStore.codes.length === 0" class="empty-state">
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
    </ul> -->
    <!-- <div v-if="searchStore.isActive && searchStore.hasNoResults" class="empty-state">
        <i class="icon-curriculum"></i>
        <p>No Curriculum in: {{ currentSelectedNodeTitle }} match your current search.</p>
        <button @click.prevent="onClearSearchSelected" @keyup.enter="onClearSearchSelected" title="Clear the current search">Clear search</button>
    </div>
    <ul v-else ref="nodesContainerEl">
        <cs-modal-code
            v-for="node in modalActiveCodesStore.codes"
            :key="node.id"
            :node="node"
            :depth="depth"
        />
    </ul> -->
    <ul ref="nodesContainerEl">
        <cs-modal-code
            v-for="node in modalActiveCodesStore.codes"
            :key="node.id"
            :node="node"
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
    overflow-x: hidden; /*prevent any horizontal animations from showing a scrollbar */
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