<script setup>

/**
 * To indicate progress through the node tree and to be able to
 * move backwards through the tree
 */

import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';
import { useActiveNodeStore } from '../activeNode/store/mcActiveNodeStore';


const viewNodesStore = useViewNodesStore();
const activeNodeStore = useActiveNodeStore();

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
    <ul class="breadcrumb">
        <li v-for="(breadcrumb) in viewNodesStore.breadcrumbs">
            <a
                :class="`${ activeNodeStore.isActive ? 'disabled' : '' }`"
                :href="`/curricululm/${breadcrumb.id}`"
                :title="`View ${breadcrumb.name}`"
                @click.prevent="onSelected(breadcrumb.depth, breadcrumb.id)"
                @keyup.enter="onSelected(breadcrumb.depth, breadcrumb.id)"
            >
                {{ breadcrumb.name }}
            </a>
        </li>
    </ul>
</template>

<style lang="scss" scoped>

.disabled {
    opacity: 0.5;
}

</style>