<script setup>

/**
 * To be able to delete a node on confirmation
 */

import { ref, computed, watch } from 'vue';
import { useSelectedNodesStore } from './store/mcSelectedNodesStore';
import { useDeleteNodesStore } from './store/mcDeleteNodesStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


const selectedNodesStore = useSelectedNodesStore();
const deleteNodesStore = useDeleteNodesStore();
const modalEl = ref(null);

/**
 * to open the modal when requested
 */
watch(() => deleteNodesStore.isOpen, (isOpen) => {
    isOpen ? modalEl.value.open() : modalEl.value.close();
});

/**
 * delete the node when confirmed
 */
function onDeleteSelected () {
    deleteNodesStore.delete();
}

/**
 * so we can track the progress of the node removal
 * and update the UI accordingly
 */
deleteNodesStore.$onAction(({ name, after, onError }) => {
    if (name === 'delete') {
        // store.toastEl.activate('Disbanding...');
        after((result) => {
            console.warn('success');
            // store.toastEl.timeout('Group successfully disbanded', true, 5000);
        });
        onError((error) => {
            console.warn(error);
            // store.toastEl.timeout('Unable to delete node', false, 5000);
        });
    }
});

/**
 * To instantly notify the user that the node is being deleted
 * by updating the button text
 *
 * @returns { String }
 */
const deleteButtonText = computed(() => deleteNodesStore.isProcessing ? 'Deleting...' : 'Yes');

/**
 * to prevent the user from moving away from the modal while
 * processing is taking place
 *
 * @returns { Boolean }
 */
const isModalDisabled = computed(() => deleteNodesStore.isProcessing);

/**
 * to close the modal
 */
function onCancelSelected () {
    deleteNodesStore.cancel();
}

/**
 * ensure the isOpen is kept up to date
 * as we can currently also use foundation to close the modal
 */
function onModalClosed () {
    deleteNodesStore.cancel();
}

</script>

<template>
    <reveal-modal
        ref="modalEl"
        :is-close-disabled="isModalDisabled"
        :close-on-background-click="false"
        @closed="onModalClosed"
    >
        <h2>Please Confirm</h2>
        <p>Are you sure you wish to delete the {{ selectedNodesStore.countWithName }} selected and all of {{ selectedNodesStore.count > 1 ? 'their' : 'its' }} children?</p>
        <ul class="flex-list">
            <li>
                <button @click.prevent="onDeleteSelected" class="button icon-approve submit" :disabled="deleteNodesStore.isProcessing">{{ deleteButtonText }}</button>
            </li>
            <li>
                <button @click.prevent="onCancelSelected" class="button icon-close" :disabled="deleteNodesStore.isProcessing">Cancel</button>
            </li>
        </ul>
    </reveal-modal>
</template>

<style scoped>

</style>
