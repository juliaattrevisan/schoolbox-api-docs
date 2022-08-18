<script setup>

/**
 * The <select> dropdowns that incrementally display as we
 * move further down the curriculum tree
 */

import { computed } from 'vue';
import { useActiveCodesStore } from './store/mcActiveCodesStore';


const activeCodesStore = useActiveCodesStore();

/**
 * To reset the selectedSecondNodeId when there's a change in the first
 */
function onFirstNodeChanged (event) {
    // activeCodesStore.resetSelectedSecondNode();
    // activeCodesStore.setNextSelectedNode(event.target.value);
}

/**
 * Only show the <select> if a first node has been selected, and there's nodes within to show
 *
 * @returns { Boolean }
 */
// const showSecondNodeSelect = computed(() => activeCodesStore.hasSelectedFirstNode && activeCodesStore.hasSecondNodes);

/**
 * To reset the selectedThirdNodeId when there's a change in the second
 */
// function onSecondNodeChanged () {
//     activeCodesStore.resetSelectedThirdNode();
// }

/**
 * Only show the <select> if a second node has been selected, and there's nodes within to show
 *
 * @returns { Boolean }
 */
// const showThirdNodeSelect = computed(() => activeCodesStore.hasSelectedSecondNode && activeCodesStore.hasThirdNodes);

/**
 * To reset the selectedFourthNodeId when there's a change in the third
 */
// function onThirdNodeChanged () {
//     activeCodesStore.resetSelectedFourthNode();
// }

/**
 * Only show the <select> if a third node has been selected, and there's nodes within to show
 *
 * @returns { Boolean }
 */
// const showFourthNodeSelect = computed(() => activeCodesStore.hasSelectedThirdNode && activeCodesStore.hasFourthNodes);

</script>

<template>
    <div class="first-node-container">
        <select @change="onFirstNodeChanged" :disabled="activeCodesStore.hasSingleGroup">
            <option v-for="node in activeCodesStore.getNodesAt(0)" :key="node.id" :value="node.id">{{ node.name }}</option>
        </select>
    </div>
    <!-- <div class="other-nodes-container">
        <select v-if="showSecondNodeSelect" v-model="activeCodesStore.selectedSecondNodeId" @change="onSecondNodeChanged">
            <option v-for="node in activeCodesStore.secondNodes" :key="node.id" :value="node.id">{{ node.name }}</option>
        </select>
        <select v-if="showThirdNodeSelect" v-model="activeCodesStore.selectedThirdNodeId" @change="onThirdNodeChanged">
            <option v-for="node in activeCodesStore.thirdNodes" :key="node.id" :value="node.id">{{ node.name }}</option>
        </select>
        <select v-if="showFourthNodeSelect" v-model="activeCodesStore.selectedFourthNodeId">
            <option v-for="node in activeCodesStore.fourthNodes" :key="node.id" :value="node.id">{{ node.name }}</option>
        </select>
   </div> -->
</template>

<style lang="scss" scoped>

$minColWidth: 15rem;

.other-nodes-container {
    display: grid;
    gap: 0.5rem;
    //allow the columns to just fill so we maximise space regargless of how many are currently visible
    grid-template-columns: repeat(auto-fit, minmax($minColWidth, 1fr));
}

</style>