<script setup>

/**
 * To be able to enabled a node on confirmation
 */

import { ref, computed, watch } from 'vue';
import { useStore } from '../store/manageCurriculumStore';
import { useSelectedNodesStore } from './store/mcSelectedNodesStore';
import { useEnableNodesStore } from './store/mcEnableNodesStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


const store = useStore();
const selectedNodesStore = useSelectedNodesStore();
const enableNodesStore = useEnableNodesStore();
const modalEl = ref(null);

/**
 * to open the modal when requested
 */
watch(() => enableNodesStore.isOpen, (isOpen) => {
    isOpen ? modalEl.value.open() : modalEl.value.close();
});

/**
 * enabled the node when confirmed
 */
function onEnableSelected () {
    enableNodesStore.enable();
}

/**
 * so we can track the progress of the node removal
 * and update the UI accordingly
 */
enableNodesStore.$onAction(({ name, after, onError }) => {
    if (name === 'enable') {
        store.toastEl.activate('Processing...');
        after((result) => {
            store.toastEl.timeout(result, true, 5000);
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
    if (enableNodesStore.isProcessing && enableNodesStore.enabled) {
        return 'Enabling...';
    } else if (enableNodesStore.isProcessing && !enableNodesStore.enabled) {
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
const isModalDisabled = computed(() => enableNodesStore.isProcessing);

/**
 * to close the modal
 */
function onCancelSelected () {
    enableNodesStore.cancel();
}

/**
 * ensure the isOpen is kept up to date
 * as we can currently also use foundation to close the modal
 */
function onModalClosed () {
    enableNodesStore.cancel();
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
        <p v-if="enableNodesStore.enabled">
            Are you sure you wish to enable the {{ selectedNodesStore.countWithName }} selected and all of {{ selectedNodesStore.count > 1 ? 'their' : 'its' }} children?
            If you continue, these nodes will be visible from the curriculum code selector during curriculum mapping.
        </p>
        <p v-else>
            Are you sure you wish to disable the {{ selectedNodesStore.countWithName }} selected and all of {{ selectedNodesStore.count > 1 ? 'their' : 'its' }} children?
            If you continue, these nodes will be hidden from the curriculum code selector during curriculum mapping.
        </p>
        <ul class="flex-list">
            <li>
                <button @click.prevent="onEnableSelected" class="button icon-approve submit" :disabled="enableNodesStore.isProcessing">{{ enabledButtonText }}</button>
            </li>
            <li>
                <button @click.prevent="onCancelSelected" class="button icon-close" :disabled="enableNodesStore.isProcessing">Cancel</button>
            </li>
        </ul>
    </reveal-modal>
</template>

<style scoped>

</style>
