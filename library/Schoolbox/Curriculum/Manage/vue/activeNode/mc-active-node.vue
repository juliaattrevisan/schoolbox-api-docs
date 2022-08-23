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
 * open a modal confirming whether to delete this node
 */
function onCloseSelected () {
    activeNodeStore.unset();
}

/**
 * open the edit modal
 */
function onEditSelected () {
    console.warn('onEditSelected');
}

/**
 * add another subsection to this node
 */
function onAddSubsectionSelected () {
    console.warn('onAddSubsectionSelected');
}

/**
 * hide this node from the curriculum
 */
function onDisableSelected () {
    console.warn('onDisableSelected');
}

/**
 * open a modal confirming whether to delete this node
 */
function onDeleteSelected () {
    deleteNodeStore.prompt();
}

</script>

<template>
    <div class="active-node-container">
        <h2>{{ activeNodeStore.title }}</h2>
        <button @click.prevent="onCloseSelected" @keyup.enter="onCloseSelected" title="Close">Close</button>
        <p class="meta">{{ activeNodeStore.node.breadcrumb }}</p>
        <p v-if="activeNodeStore.node.reference"><b>Reference:</b> <span v-html="activeNodeStore.node.reference"></span></p>
        <p>{{ activeNodeStore.node.description }}</p>
        <ul class="flex-list buttons">
            <li><button @click.prevent="onEditSelected" @keyup.enter="onEditSelected" class="button icon-modify" :disabled="false">Edit</button></li>
            <li><button @click.prevent="onAddSubsectionSelected" @keyup.enter="onAddSubsectionSelected" class="button icon-add" :disabled="false">Add subsection</button></li>
            <li><button @click.prevent="onDisableSelected" @keyup.enter="onDisableSelected" class="button icon-view-hollow" :disabled="false">Disable</button></li>
            <li><button @click.prevent="onDeleteSelected" @keyup.enter="onDeleteSelected" class="button icon-delete" :disabled="false">Delete</button></li>
        </ul>
    </div>
</template>

<style lang="scss" scoped>

.active-node-container {
    border-bottom: 1px solid var(--content-ui-background);
    border-top: 1px solid var(--content-ui-background);
    margin: 1rem 0rem;
    padding: 1rem 0.5rem;
}

</style>