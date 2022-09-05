<script setup>

/**
 * To be able to enabled a node on confirmation
 */

import { ref, computed, watch } from 'vue';
import { useSelectedNodesStore } from './store/mcSelectedNodesStore';
import { useEnableSelectedNodesStore } from './store/mcEnableSelectedNodesStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


const selectedNodesStore = useSelectedNodesStore();
const enableSelectedNodesStore = useEnableSelectedNodesStore();
const modalEl = ref(null);

/**
 * to open the modal when requested
 */
watch(() => enableSelectedNodesStore.isOpen, (isOpen) => {
    isOpen ? modalEl.value.open() : modalEl.value.close();
});

/**
 * enabled the node when confirmed
 */
function onEnableSelected () {
    enableSelectedNodesStore.enable();
}

/**
 * so we can track the progress of the node removal
 * and update the UI accordingly
 */
enableSelectedNodesStore.$onAction(({ name, after, onError }) => {
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
 * To instantly notify the user that the node is being enabled / disabled
 * by updating the button text
 *
 * @returns { String }
 */
const enabledButtonText = computed(() => {
    if (enableSelectedNodesStore.isProcessing && enableSelectedNodesStore.enabled) {
        return 'Enabling...';
    } else if (enableSelectedNodesStore.isProcessing && !enableSelectedNodesStore.enabled) {
        return 'Disabling...';
    } else {
        return 'Yes';
    }
});

/**
 * to prevent the user from moving away from the modal while
 * processing is taking place
 *
 * @returns { Boolean }
 */
const isModalDisabled = computed(() => enableSelectedNodesStore.isProcessing);

/**
 * to close the modal
 */
function onCancelSelected () {
    enableSelectedNodesStore.cancel();
}

/**
 * ensure the isOpen is kept up to date
 * as we can currently also use foundation to close the modal
 */
function onModalClosed () {
    enableSelectedNodesStore.cancel();
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
        <p v-if="enableSelectedNodesStore.enabled">
            Are you sure you wish to enable the {{ selectedNodesStore.countWithName }} selected and all of {{ selectedNodesStore.count > 1 ? 'their' : 'its' }} children?
            If you continue, these nodes will be visible from the curriculum code selector during curriclum mapping.
        </p>
        <p v-else>
            Are you sure you wish to disable the {{ selectedNodesStore.countWithName }} selected and all of {{ selectedNodesStore.count > 1 ? 'their' : 'its' }} children?
            If you continue, these nodes will be hidden from the curriculum code selector during curriclum mapping.
        </p>
        <ul class="flex-list buttons">
            <li>
                <button @click.prevent="onEnableSelected" class="button submit" :disabled="enableSelectedNodesStore.isProcessing">{{ enabledButtonText }}</button>
            </li>
            <li>
                <button @click.prevent="onCancelSelected" class="button" :disabled="enableSelectedNodesStore.isProcessing">Cancel</button>
            </li>
        </ul>
    </reveal-modal>
</template>

<style scoped>

</style>
