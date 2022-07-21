<script setup>

/**
 * The modal that's used to select curriculum
 */

import { ref, computed, watch } from 'vue';
import { useModalStore } from '../store/csModalStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';
import CsModalHeader from './cs-modal-header.vue';
import CsModalResults from './cs-modal-results.vue';
import CsModalFooter from './cs-modal-footer.vue';


const modalStore = useModalStore();
const modalEl = ref(null);

/**
 * to open the modal when requested
 */
watch(() => modalStore.isOpen, (newIsOpen) => {
    newIsOpen ? modalEl.value.open() : modalEl.value.close();
});

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