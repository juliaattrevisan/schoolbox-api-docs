<script setup>

/**
 * The <select> dropdowns that incrementally display as we
 * move further down the curriculum tree
 */

import { useActiveCodesStore } from './store/mcActiveCodesStore';


const activeCodesStore = useActiveCodesStore();

/**
 * to display the just selected node
 *
 * @param { number }
 * @param { Event }
 */
function onNodeChanged (depth, event) {
    activeCodesStore.setSelectedNode(depth, parseInt(event.target.value));
}

</script>

<template>
    <div class="nodes-container">
        <select
            v-for="(id, index) in activeCodesStore.nodeListsLimited"
            @change="onNodeChanged(index, $event)"
            :key="index"
            :disabled="index === 0 && activeCodesStore.hasSingleGroup"
        >
            <option
                v-for="node in activeCodesStore.getChildrenAt(index)"
                :key="node.id"
                :value="node.id"
                :selected="activeCodesStore.selectedNodeIdList[index] === node.id"
            >
                {{ node.name }}
            </option>
        </select>
    </div>
</template>

<style lang="scss" scoped>

$minColWidth: 15rem;

.nodes-container {
    display: grid;
    gap: 0.5rem;
    //allow the columns to just fill so we maximise space regardless of how many are currently visible
    grid-template-columns: repeat(3, minmax($minColWidth, 1fr));
    margin-bottom: 0.5rem;
}

select {
    margin-bottom: 0;
}

</style>