<script setup>

/**
 * To be able to enabled a node on confirmation
 */

import { ref, computed, watch } from 'vue';
import { useActiveNodeStore } from './store/mcActiveNodeStore';
import { useEnableNodeStore } from './store/mcEnableNodeStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


const activeNodeStore = useActiveNodeStore();
const enabledNodeStore = useEnableNodeStore();
const modalEl = ref(null);

/**
 * to open the modal when requested
 */
watch(() => enabledNodeStore.isOpen, (isOpen) => {
    isOpen ? modalEl.value.open() : modalEl.value.close();
});

/**
 * also highlight that you'll be deleting all the children within as well
 *
 * @returns { String }
 */
const childrenWarning = computed(() => activeNodeStore?.node?.children.length > 0 ? ' and all of its children' : '');

/**
 * enabled the node when confirmed
 */
function onEnableSelected () {
    enabledNodeStore.enable();
}

/**
 * so we can track the progress of the node removal
 * and update the UI accordingly
 */
enabledNodeStore.$onAction(({ name, after, onError }) => {
    if (name === 'enabled') {
        // store.toastEl.activate('Disbanding...');
        after(() => {
            console.warn('success');
            // store.toastEl.timeout('Group successfully disbanded', true, 5000);
        });
        onError((error) => {
            console.warn(error);
            // store.toastEl.timeout('Unable to enabled node', false, 5000);
        });
    }
});

/**
 * To instantly notify the user that the node is being enabledd
 * by updating the button text
 *
 * @returns { String }
 */
const enabledButtonText = computed(() => enabledNodeStore.isProcessing ? 'Deleting...' : 'Yes');

/**
 * to prevent the user from moving away from the modal while
 * processing is taking place
 *
 * @returns { Boolean }
 */
const isModalDisabled = computed(() => enabledNodeStore.isProcessing);

/**
 * to close the modal
 */
function onCancelSelected () {
    enabledNodeStore.cancel();
}

/**
 * ensure the isOpen is kept up to date
 * as we can currently also use foundation to close the modal
 */
function onModalClosed () {
    enabledNodeStore.cancel();
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
        <p>
            Are you sure you wish to disable this node: <strong>{{ activeNodeStore.title }} {{ childrenWarning }}?</strong>
            If you continue, this node and any child nodes will be hidden from the curriculum code selector during curriclum mapping.</p>
        <ul class="flex-list buttons">
            <li><button @click.prevent="onEnableSelected" class="button submit" :enabledd="enabledNodeStore.isProcessing">{{ enabledButtonText }}</button></li>
            <li><button @click.prevent="onCancelSelected" class="button" :enabledd="enabledNodeStore.isProcessing">Cancel</button></li>
        </ul>
    </reveal-modal>
</template>

<style scoped>

</style>
