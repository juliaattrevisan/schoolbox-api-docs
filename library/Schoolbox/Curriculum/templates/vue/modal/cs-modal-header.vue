<script setup>

/**
 * The modal header, containing the title, select nodes and search input
 */

import { computed } from 'vue';
import { useModalActiveCodesStore } from '../store/csModalActiveCodesStore';


const modalActiveCodesStore = useModalActiveCodesStore();

/**
 * To reset the selectedSecondNodeId when there's a change in the first
 */
function onFirstNodeChanged () {
    modalActiveCodesStore.resetSelectedSecondNode();
}

/**
 * Only show the <select> if a first node has been selected, and there's nodes within to show
 *
 * @returns { Boolean }
 */
const showSecondNodeSelect = computed(() => modalActiveCodesStore.hasSelectedFirstNode && modalActiveCodesStore.hasSecondNodes);

/**
 * To reset the selectedThirdNodeId when there's a change in the second
 */
function onSecondNodeChanged () {
    modalActiveCodesStore.resetSelectedThirdNode();
}

/**
 * Only show the <select> if a second node has been selected, and there's nodes within to show
 *
 * @returns { Boolean }
 */
const showThirdNodeSelect = computed(() => modalActiveCodesStore.hasSelectedSecondNode && modalActiveCodesStore.hasThirdNodes);

</script>

<template>
   <h2>Select Curriculum Codes</h2>
   <div class="node-selects">
        <select v-model="modalActiveCodesStore.selectedFirstNodeId" @change="onFirstNodeChanged">
            <option v-for="node in modalActiveCodesStore.firstNodes" :key="node.id" :value="node.id">{{ node.name }}</option>
        </select>
        <select v-if="showSecondNodeSelect" v-model="modalActiveCodesStore.selectedSecondNodeId" @change="onSecondNodeChanged">
            <option v-for="node in modalActiveCodesStore.secondNodes" :key="node.id" :value="node.id">{{ node.name }}</option>
        </select>
        <select v-if="showThirdNodeSelect"  v-model="modalActiveCodesStore.selectedThirdNodeId">
            <option v-for="node in modalActiveCodesStore.thirdNodes" :key="node.id" :value="node.id">{{ node.name }}</option>
        </select>
   </div>
   <!-- <input
        v-if="true"
        v-model="modalActiveCodesStore.query"
        ref="queryInputEl"
        type="text"
        class="search-input"
        :readonly="false"
        placeholder="Search for codes or keywords..."
        @input="$event.target.composing = false"
    /> -->
</template>

<style scoped>

.node-selects {
    display: grid;
    gap: 0.5rem;
    grid-template-columns: repeat(3, 1fr);
}

</style>