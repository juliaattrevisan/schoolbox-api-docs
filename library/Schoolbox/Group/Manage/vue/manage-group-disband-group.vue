<script setup>

/**
 * To be able to remove selected members from the group
 */

import { ref, computed, watch } from 'vue';
import { timeout } from '@js/util/jsUtils';
import { useStore } from './store/manageGroupStore';
import { useDisbandStore } from './store/manageGroupDisbandStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


const store = useStore();
const disbandStore = useDisbandStore();
const removeModalEl = ref(null);
const hasDisbanded = ref(false); //to toggle the successful disband note

/**
 * to open the modal when requested
 */
watch(() => disbandStore.isPromptOpen, (newIsOpen) => {
    newIsOpen ? removeModalEl.value.open() : removeModalEl.value.close();
});

/**
 * disband the group confirmed
 */
function onDisbandGroupSelected () {
    disbandStore.disbandGroup();
}

/**
 * so we can track the progress of the member removal
 * and update the UI accordingly
 */
disbandStore.$onAction(({ name, after, onError }) => {
    if (name === 'disbandGroup') {
        store.toastEl.activate('Disbanding...');
        after(() => {
            hasDisbanded.value = true;
            redirect();
        });
        onError((error) => {
            console.warn(error);
            store.toastEl.timeout('Unable to disband group', false, 5000);
        });
    }
});

/**
 * redirect to the appropriate page once the group has been disbanded
 */
async function redirect () {
    await timeout(5000);
    window.location = `/homepage/${store.id}`
}


/**
 * To instantly notify the user that the members are being removed
 * by updating the button text
 *
 * @returns { String }
 */
const disbandButtonText = computed(() => disbandStore.isProcessing ? 'Disbanding...' : 'Yes');

/**
 * to prevent the user from moving away from the modal while
 * processing is taking place or the disband is a success and the
 * user is being redirected
 *
 * @returns { Boolean }
 */
const isCloseModalDisabled = computed(() => disbandStore.isProcessing || hasDisbanded.value);

/**
 * to close the modal
 */
function onCancelSelected () {
    disbandStore.cancelDisbandGroup();
}

/**
 * ensure the isPromptOpen is kept up to date
 * as we can currently also use foundation to close the modal
 */
function onModalClosed () {
    disbandStore.cancelDisbandGroup();
}

</script>

<template>
    <reveal-modal
        ref="removeModalEl"
        :is-close-disabled="isCloseModalDisabled"
        :close-on-background-click="false"
        @closed="onModalClosed"
    >
        <h2>Please Confirm</h2>
        <p>Are you sure you wish to disband this group? All members of the group will be removed, but all homepage content will remain.</p>
        <div v-if="hasDisbanded" class="alert-box success">Group successfully disbanded, redirecting...</div>
        <ul class="flex-list buttons">
            <li><button @click.prevent="onDisbandGroupSelected" class="button submit" :disabled="disbandStore.isProcessing || hasDisbanded">{{ disbandButtonText }}</button></li>
            <li><button @click.prevent="onCancelSelected" class="button" :disabled="disbandStore.isProcessing || hasDisbanded">Cancel</button></li>
        </ul>
    </reveal-modal>
</template>

<style scoped>

</style>
