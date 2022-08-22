<script setup>

/**
 * The <select> dropdowns that incrementally display as we
 * move further down the curriculum nodes
 */

import { useVisibleNodesStore } from './store/mcVisibleNodesStore';


const visibleNodesStore = useVisibleNodesStore();

/**
 * to display the just selected node
 *
 * @param { number }
 * @param { Event }
 */
function onNodeChanged (depth, event) {
    visibleNodesStore.setSelected(depth, parseInt(event.target.value));
}

</script>

<template>
    <div class="nodes-container">
        <select
            v-for="(id, index) in visibleNodesStore.listsLimited"
            @change="onNodeChanged(index, $event)"
            :key="index"
            :disabled="index === 0 && visibleNodesStore.hasSingleCurriculum"
        >
            <option
                v-for="node in visibleNodesStore.getChildrenAt(index)"
                :key="node.id"
                :value="node.id"
                :selected="visibleNodesStore.idList[index] === node.id"
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