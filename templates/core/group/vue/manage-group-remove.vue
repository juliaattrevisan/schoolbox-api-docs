<script setup>

/**
 * To be able to remove selected members from the group
 */

import { ref, computed, watch } from 'vue';
import { useRemoveStore } from './store/manageGroupRemoveStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


const props = defineProps({
    /**
     * To display the toast access status changes
     */
    toastEl: {
        required: true,
    },
});

const store = useRemoveStore();
const removeModalEl = ref(null);
const isProcessing = ref(false);

/**
 * to open the modal when requested
 */
watch(() => store.isRemoveSelectedMembersPromptOpen, (newIsOpen) => {
    newIsOpen ? removeModalEl.value.open() : removeModalEl.value.close();
});

/**
 * remove the selected members
 */
function onRemoveSelected () {
    store.removeSelectedMembers();
}

/**
 * so we can track the progress of the member removal 
 * and update the UI accordingly
 */
store.$onAction(({ name, after, onError }) => {
    if (name === 'removeSelectedMembers') {
        isProcessing.value = true;
        props.toastEl.activate('Removing...');
        after(() => {
            isProcessing.value = false;
            props.toastEl.timeout('Members successfully removed', true, 5000);
        });
        onError((error) => {
            console.warn(error);
            isProcessing.value = false;
            props.toastEl.timeout('Unable to remove members', false, 5000);
        });
    }
});

/**
 * To instantly notify the user that the members are being removed 
 * by updating the button text
 *
 * @returns { String }
 */
const removeButtonText = computed(() => isProcessing.value ? 'Removing...' : 'Remove');

/**
 * to prevent the user from moving away from the modal while 
 * processing is taking place
 *
 * @returns { Boolean }
 */
const isCloseModalDisabled = computed(() => isProcessing.value);

/**
 * to close the modal 
 */
function onCancelSelected () {
    store.cancelRemoveSelectedMembers();
}

/**
 * ensure the isRemoveSelectedMembersPromptOpen is kept up to date
 * as we can currently also use foundation to close the modal 
 */
function onModalClosed () {
    store.cancelRemoveSelectedMembers();
}

</script>

<template>
    <reveal-modal ref="removeModalEl" 
        :is-close-disabled="isCloseModalDisabled" 
        :close-on-background-click="false" 
        @closed="onModalClosed"
    >
        <h2>Please Confirm</h2>
        <p>Are you sure you wish to remove the selected members?</p>
        <ul class="flex-list buttons">
            <li><button @click.prevent="onRemoveSelected" class="button submit" :disabled="isProcessing">{{ removeButtonText }}</button></li>
            <li><button @click.prevent="onCancelSelected" class="button" :disabled="isProcessing">Cancel</button></li>
        </ul>
    </reveal-modal>
</template>

<style scoped>

</style>