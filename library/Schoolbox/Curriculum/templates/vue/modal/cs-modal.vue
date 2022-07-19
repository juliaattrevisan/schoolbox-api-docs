<script setup>

/**
 * The modal that's used to select curriculum
 */

import { ref, computed, watch } from 'vue';
// import { useStore } from '../store/curriculumSelectorStore';
import { useModalStore } from '../store/csModalStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';
import CsModalHeader from './cs-modal-header.vue';
import CsModalResults from './cs-modal-results.vue';
import CsModalFooter from './cs-modal-footer.vue';


// const store = useStore();
const modalStore = useModalStore();
const modalEl = ref(null);

/**
 * to open the modal when requested
 */
watch(() => modalStore.isOpen, (newIsOpen) => {
    newIsOpen ? modalEl.value.open() : modalEl.value.close();
});

/**
 * remove the selected members
 */
// function onRemoveSelected () {
//     removeMembersStore.removeSelectedMembers();
// }

/**
 * so we can track the progress of the member removal 
 * and update the UI accordingly
 */
// removeMembersStore.$onAction(({ name, after, onError }) => {
//     if (name === 'removeSelectedMembers') {
//         store.toastEl.activate('Removing...');
//         after(() => {
//             store.toastEl.timeout('Members successfully removed', true, 5000);
//         });
//         onError((error) => {
//             console.warn(error);
//             store.toastEl.timeout('Unable to remove members', false, 5000);
//         });
//     }
// });

/**
 * To instantly notify the user that the members are being removed 
 * by updating the button text
 *
 * @returns { String }
 */
// const removeButtonText = computed(() => removeMembersStore.isProcessing ? 'Removing...' : 'Remove');

/**
 * to prevent the user from moving away from the modal while 
 * processing is taking place
 *
 * @returns { Boolean }
 */
// const isCloseModalDisabled = computed(() => removeMembersStore.isProcessing);

/**
 * to close the modal 
 */
// function onCancelSelected () {
//     removeMembersStore.cancelRemoveSelectedMembers();
// }

/**
 * ensure the isPromptOpen is kept up to date
 * as we can currently also use foundation to close the modal 
 */
function onModalClosed () {
    // removeMembersStore.cancelRemoveSelectedMembers();
}

</script>

<template>
    <reveal-modal 
        ref="modalEl" 
        :is-close-disabled="false" 
        :close-on-background-click="false" 
        @closed="onModalClosed"
    >
        <cs-modal-header />
        <cs-modal-results />
        <cs-modal-footer />
    </reveal-modal>
</template>

<style scoped>

</style>