<script setup>

/**
 * to show further info in a modal on a code that's been selected
 */

import { ref, computed, watch } from 'vue';
import { useActiveNodeStore } from './store/mcActiveNodeStore';
import { useDeleteNodeStore } from './store/mcDeleteNodeStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


const editNodeStore = useActiveNodeStore();
const modalEl = ref(null);

/**
 * to open the modal when requested
 */
watch(() => editNodeStore.isActive, (isActive) => {
    if (isActive) {
        modalEl.value.open();
    }
});

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = computed(() => {
    if (editNodeStore.node.code && editNodeStore.node.code !== '') {
        return `[${editNodeStore.node.code}] ${editNodeStore.node.name}`;
    } else {
        return editNodeStore.node.name;
    }
});

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
    useDeleteNodeStore().prompt();
}

/**
 * close the modal, setting the edited node to null
 * so we can allow another selection
 */
function onModalClosed () {
    editNodeStore.unset(null);
}

</script>

<template>
    <reveal-modal
        ref="modalEl"
        size="medium"
        @closed="onModalClosed"
    >
        <template v-if="editNodeStore.isActive">
            <h2>{{ title }}</h2>
            <p class="meta">{{ editNodeStore.node.breadcrumb }}</p>
            <p v-if="editNodeStore.node.reference"><b>Reference:</b> <span v-html="editNodeStore.node.reference"></span></p>
            <p>{{ editNodeStore.node.description }}</p>
            <ul class="flex-list buttons">
                <li><button @click.prevent="onEditSelected" @keyup.enter="onEditSelected" class="button icon-modify" :disabled="false">Edit</button></li>
                <li><button @click.prevent="onAddSubsectionSelected" @keyup.enter="onAddSubsectionSelected" class="button icon-add" :disabled="false">Add subsection</button></li>
                <li><button @click.prevent="onDisableSelected" @keyup.enter="onDisableSelected" class="button icon-view-hollow" :disabled="false">Disable</button></li>
                <li><button @click.prevent="onDeleteSelected" @keyup.enter="onDeleteSelected" class="button icon-delete" :disabled="false">Delete</button></li>
            </ul>
        </template>
    </reveal-modal>
</template>

<style scoped>

</style>