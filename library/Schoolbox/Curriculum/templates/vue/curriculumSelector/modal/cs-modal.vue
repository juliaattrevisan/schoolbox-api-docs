<script setup>
/**
 * The modal that's used to help select curriculum
 */

import { ref, watch } from "vue";
import { useModalStore } from "../store/csModalStore";
import { useModalNodeUsageStore } from "../store/csModalNodeUsageStore";
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import CsModalHeader from "./cs-modal-header.vue";
import CsModalNodes from "./cs-modal-nodes.vue";
import CsModalFooter from "./cs-modal-footer.vue";

const modalStore = useModalStore();
const modalEl = ref(null);
const modalNodeUsageStore = useModalNodeUsageStore();

/**
 * to open the modal when requested
 */
watch(
    () => modalStore.isOpen,
    (newIsOpen) => {
        newIsOpen ? modalEl.value.open() : modalEl.value.close();
    }
);

/**
 * close the modal
 */
function onModalClosed() {
    modalStore.cancel();
    if (modalNodeUsageStore.isOpen) {
        modalNodeUsageStore.cancel();
    }
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
        <cs-modal-nodes />
        <cs-modal-footer />
    </reveal-modal>
</template>

<style scoped></style>
