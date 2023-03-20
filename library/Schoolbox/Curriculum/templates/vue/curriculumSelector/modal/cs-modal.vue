<script setup>
/**
 * The modal that's used to help select curriculum
 */

import { ref } from "vue";
import { useModalStore } from "../store/csModalStore";
import { useModalNodeUsageStore } from "../store/csModalNodeUsageStore";
import SequentialRevealModal from "@schoolboxVue/components/sequential-reveal-modal.vue";
import CsModalHeader from "./cs-modal-header.vue";
import CsModalNodes from "./cs-modal-nodes.vue";
import CsModalFooter from "./cs-modal-footer.vue";

const modalStore = useModalStore();
const modalEl = ref(null);
const isNextSelected = ref(false);
const modalNodeUsageStore = useModalNodeUsageStore();

/**
 * close the modal, also closing the node usage if it's open
 */
function onModalClosed() {
    modalStore.cancel();
    if (modalNodeUsageStore.isOpen) {
        modalNodeUsageStore.cancel();
    }
}
</script>

<template>
    <sequential-reveal-modal
        ref="modalEl"
        size="large"
        :is-close-disabled="false"
        :close-on-background-click="false"
        :is-open="modalStore.isOpen"
        :is-next-selected="isNextSelected"
        @next-opened="isNextSelected = false"
        @closed="onModalClosed"
    >
        <template #sequence-1>
            <cs-modal-header />
            <cs-modal-nodes />
            <cs-modal-footer />
        </template>
    </sequential-reveal-modal>
</template>

<style scoped></style>
