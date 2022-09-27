<script setup>

/**
 * The filtered results from using the <selects> or search <input>
 * From here curriclum codes can be selected
 */

import { ref, computed, watch } from 'vue';
import { useModalActiveCodesStore } from '../store/csModalActiveCodesStore';
import { useCodesStore } from '../store/csCodesStore';
import { useModalSearchStore } from '../store/csModalSearchStore';
import LoadingTable from '@schoolboxVue/components/loading/loading-table.vue';
import CsModalCode from './cs-modal-code.vue'


const modalActiveCodesStore = useModalActiveCodesStore();
const modalSearchStore = useModalSearchStore();
const codesStore = useCodesStore();
const depth = ref(0); //to increment each time we go down the node tree to visually indicate children
const codesContainerEl = ref(null); //to scroll back to top on any selection change


/**
 * To further clarify where the current search term hasn't found any code matches
 *
 * @returns { String }
 */
const currentSelectedNodeTitle = computed(() => modalActiveCodesStore.current.name);

/**
 * Clear the current search query
 */
function onClearSearchSelected () {
    modalSearchStore.clear();
}

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
    <div v-if="modalSearchStore.isProcessing">
        <loading-table />
    </div>
    <div v-else-if="codesStore.hasNoCurriculum" class="empty-state">
        <i class="icon-curriculum"></i>
        <p>There's currently no Curriculum loaded to display.</p>
    </div>
    <div v-else-if="codesStore.isActive && modalSearchStore.hasNoResults" class="empty-state">
        <i class="icon-curriculum"></i>
        <p>No Curriculum in: {{ currentSelectedNodeTitle }} match your current search.</p>
        <button @click.prevent="onClearSearchSelected" @keyup.enter="onClearSearchSelected" title="Clear the current search">Clear search</button>
    </div>
    <ul v-else ref="nodesContainerEl">
        <cs-modal-code
            v-for="code in modalActiveCodesStore.codes"
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