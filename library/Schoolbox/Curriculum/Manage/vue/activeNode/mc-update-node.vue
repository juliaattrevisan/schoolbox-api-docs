<script setup>

/**
 * to show further info in a modal on a code that's been selected
 */

import { ref, watch } from 'vue';
import { useActiveNodeStore } from './store/mcActiveNodeStore';
import { useDeleteNodeStore } from './store/mcDeleteNodeStore';


const activeNodeStore = useActiveNodeStore();
const deleteNodeStore = useDeleteNodeStore();

/**
 * open the edit modal
 */
function onSaveSelected () {
    console.warn('onSaveSelected');
}

</script>

<template>
    <div class="update-node-container">
        <h2>{{ activeNodeStore.title }}</h2>
        <p class="meta">{{ activeNodeStore.node.breadcrumb }}</p>
        <p v-if="activeNodeStore.node.reference"><b>Reference:</b> <span v-html="activeNodeStore.node.reference"></span></p>
        <p>{{ activeNodeStore.node.description }}</p>
        <ul class="flex-list buttons">
            <li><button @click.prevent="onSaveSelected" @keyup.enter="onSaveSelected" class="button icon-modify" :disabled="true">Save</button></li>
        </ul>
    </div>
</template>

<style lang="scss" scoped>

.update-node-container {
    border-bottom: 1px solid var(--content-ui-background);
    border-top: 1px solid var(--content-ui-background);
    margin: -1px 0 1rem 0;
    padding: 1rem 0;
}

</style>