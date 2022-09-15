<script setup>

/**
 * The filtered results from using the <selects> or search <input>
 * From here curriclum codes can be selected for further travesal or editing
 */

import { ref, computed, watch } from 'vue';
import { useStore } from '../store/manageCurriculumStore';
import { useNodesStore } from '../store/mcNodesStore';
import { useViewNodesStore } from './store/mcViewNodesStore';
import { useAddCurriculumStore } from '../store/mcAddCurriculumStore';
import McViewNode from './mc-view-node.vue'


const store = useStore();
const nodesStore = useNodesStore();
const viewNodesStore = useViewNodesStore();
const addCurriculumStore = useAddCurriculumStore();
const depth = ref(0); //to increment each time we go down the node tree to visually indicate children
const nodesContainerEl = ref(null); //to scroll back to top on any selection change


/**
 * To further clarify where the current search term hasn't found any code matches
 *
 * @returns { String }
 */
const currentSelectedNodeTitle = computed(() => viewNodesStore.current.name);

/**
 * Display the prompt to create a new curriculum
 */
function onCreateNewSelected () {
    addCurriculumStore.prompt();
}

/**
 * Clear the current search query
 */
function onClearSearchSelected () {
    viewNodesStore.clearSearch();
}

/**
 * to scroll back to top on any selection change, otherwise the
 * scroll may be at the bottom on change, which is a bit odd
 */
watch(() => viewNodesStore.nodesWithSearch, () => {
    if (nodesContainerEl.value) { //may not always exists in the dom
        nodesContainerEl.value.scrollTop = 0;
    }
});

</script>

<template>
    <div v-if="nodesStore.hasNoCurriculum" class="empty-state">
        <i class="icon-curriculum"></i>
        <p>There's currently no Curriculum loaded to display.</p>
        <template v-if="store.hasLoadedOk && !store.isReadOnly">
            <button @click.prevent="onCreateNewSelected" @keyup.enter="onCreateNewSelected" title="Create a new Curriculum">Create a new Curriculum</button>
            <a href="/curriculum/import" title="Import Curriculum">Import Curriculum</a>
        </template>
    </div>
    <div v-else-if="viewNodesStore.hasNoSearchResults" class="empty-state">
        <i class="icon-curriculum"></i>
        <p>No Curriculum in: {{ currentSelectedNodeTitle }} match your current search.</p>
        <button @click.prevent="onClearSearchSelected" @keyup.enter="onClearSearchSelected" title="Clear the current search">Clear search</button>
    </div>
    <ul v-else ref="nodesContainerEl">
        <mc-view-node
            v-for="node in viewNodesStore.nodesWithSearch"
            :key="node.id"
            :node="node"
            :depth="depth"
        />
    </ul>
</template>

<style lang="scss" scoped>

ul {
    list-style: none;
    margin: 0 0 1rem 0;
}

.empty-state {
    align-items: center;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 200px;
    p {
        max-width: 50%;
    }
    button {
        background: none;
        font-size: 0.875rem;
    }
}

</style>