<script setup>

/**
 * To indicate progress through the node tree and to be able to
 * move backwards through the tree
 */

import { useViewNodesStore } from './store/mcViewNodesStore';


const viewNodesStore = useViewNodesStore();

/**
 * to display the just selected node
 *
 * @param { number }
 * @param { number }
 */
function onSelected (depth, id) {
    viewNodesStore.setSelected(depth, parseInt(id));
}

</script>

<template>
    <div class="breadcrumbs-container">
        <button
            v-for="(id, index) in viewNodesStore.idList"
            @click.prevent="onSelected(index, id)"
            @keyup.enter="onSelected(index, id)"
        >
            {{ id }}
        </button>

    </div>
</template>

<style lang="scss" scoped>

.breadcrumbs-container {
    display: flex;
    gap: 0.5rem;
    padding: 1rem 0;
}

</style>