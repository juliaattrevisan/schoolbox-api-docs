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

/**
 * To reset the selectedFourthNodeId when there's a change in the third
 */
function onThirdNodeChanged () {
    modalActiveCodesStore.resetSelectedFourthNode();
}

/**
 * Only show the <select> if a third node has been selected, and there's nodes within to show
 *
 * @returns { Boolean }
 */
const showFourthNodeSelect = computed(() => modalActiveCodesStore.hasSelectedThirdNode && modalActiveCodesStore.hasFourthNodes);

</script>

<template>
    <div class="first-node-container">
        <select v-model="modalActiveCodesStore.selectedFirstNodeId" @change="onFirstNodeChanged" :disabled="modalActiveCodesStore.hasSingleCurriculumGroup">
            <option v-for="node in modalActiveCodesStore.firstNodes" :key="node.id" :value="node.id">{{ node.name }}</option>
        </select>
    </div>
    <div class="other-nodes-container">
        <select v-if="showSecondNodeSelect" v-model="modalActiveCodesStore.selectedSecondNodeId" @change="onSecondNodeChanged">
            <option v-for="node in modalActiveCodesStore.secondNodes" :key="node.id" :value="node.id">{{ node.name }}</option>
        </select>
        <select v-if="showThirdNodeSelect" v-model="modalActiveCodesStore.selectedThirdNodeId" @change="onThirdNodeChanged">
            <option v-for="node in modalActiveCodesStore.thirdNodes" :key="node.id" :value="node.id">{{ node.name }}</option>
        </select>
        <select v-if="showFourthNodeSelect" v-model="modalActiveCodesStore.selectedFourthNodeId">
            <option v-for="node in modalActiveCodesStore.fourthNodes" :key="node.id" :value="node.id">{{ node.name }}</option>
        </select>
   </div>
</template>

<style scoped>

.other-nodes-container {
    display: grid;
    gap: 0.5rem;
    grid-template-columns: repeat(3, 1fr);
}

</style>