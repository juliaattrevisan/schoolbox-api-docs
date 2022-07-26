<script setup>

/**
 * The modal that's used to select curriculum
 */

import { ref, watch } from 'vue';
import { useModalStore } from '../store/csModalStore';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';
import CsModalHeader from './cs-modal-header.vue';
import CsModalCodes from './cs-modal-codes.vue';
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
 * close the modal
 */
function onModalClosed () {
    modalStore.cancel();
}

</script>

<template>
    <reveal-modal 
        ref="modalEl" 
        :is-close-disabled="false" 
        :close-on-background-click="false" 
        size="large"
        @closed="onModalClosed"
    >
        <cs-modal-header />
        <cs-modal-codes />
        <cs-modal-footer />
    </reveal-modal>
</template>

<style scoped>

</style>