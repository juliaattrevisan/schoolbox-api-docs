<script setup>

/**
 * to show further info in a modal on a code that's been selected
 */

import { ref, watch } from 'vue';
import { useActiveNodeStore } from './store/mcActiveNodeStore';
import { useDeleteNodeStore } from './store/mcDeleteNodeStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


const activeNodeStore = useActiveNodeStore();
const deleteNodeStore = useDeleteNodeStore();
// const modalEl = ref(null);

/**
 * to open the modal when the active node is first set
 */
// watch(() => activeNodeStore.isActive, (isModalOpen) => {
//     if (isModalOpen) {
//         modalEl.value.open();
//     }
// });

/**
 * also open when returning from editing a node
 */
// watch(() => activeNodeStore.isUpdating, (newIsUpdating, oldIsUpdating) => {
//     if (newIsUpdating === false && oldIsUpdating === true) {
//         modalEl.value.open();
//     }
// });

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

/**
 * close the modal, setting the edited node to null
 * so we can allow another selection
 */
// function onModalClosed () {
//     activeNodeStore.unset();
// }

</script>

<template>
    <!-- <reveal-modal
        ref="modalEl"
        size="medium"
        @closed="onModalClosed"
    >
        <template v-if="activeNodeStore.isActive"> -->
            <h2>{{ activeNodeStore.title }}</h2>
            <p class="meta">{{ activeNodeStore.node.breadcrumb }}</p>
            <p v-if="activeNodeStore.node.reference"><b>Reference:</b> <span v-html="activeNodeStore.node.reference"></span></p>
            <p>{{ activeNodeStore.node.description }}</p>
            <ul class="flex-list buttons">
                <li><button @click.prevent="onEditSelected" @keyup.enter="onEditSelected" class="button icon-modify" :disabled="false">Edit</button></li>
                <li><button @click.prevent="onAddSubsectionSelected" @keyup.enter="onAddSubsectionSelected" class="button icon-add" :disabled="false">Add subsection</button></li>
                <li><button @click.prevent="onDisableSelected" @keyup.enter="onDisableSelected" class="button icon-view-hollow" :disabled="false">Disable</button></li>
                <li><button @click.prevent="onDeleteSelected" @keyup.enter="onDeleteSelected" class="button icon-delete" :disabled="false">Delete</button></li>
            </ul>
        <!-- </template>
    </reveal-modal> -->
</template>

<style scoped>

</style>