<script setup>
/**
 * The modal that's used to help select curriculum
 */

import { getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";
import SequentialRevealModal from "@schoolboxVue/components/sequential-reveal-modal.vue";
import BadgeDetail from "@schoolboxBadge/vue/badgeDetail.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details";
import { useModalStore } from "../store/csModalStore";
import { useModalNodeUsageStore } from "../store/csModalNodeUsageStore";
import CsModalHeader from "./cs-modal-header.vue";
import CsModalNodes from "./cs-modal-nodes.vue";
import CsModalFooter from "./cs-modal-footer.vue";

const modalStore = useModalStore();
const isNextSelected = window.Vue.ref(false); // to move to the next modal if a badge usage is selected
const modalNodeUsageStore = useModalNodeUsageStore();
const selectedNodeDetails = window.Vue.ref(getEmptyNode());

/**
 * to show the selected badge that has been selected in a linked usage
 * from cs-modal-node-usage-details
 */
window.Vue.watch(
    () => modalStore.selectedBadge,
    (newValue, oldValue) => {
        if (newValue && newValue !== oldValue) {
            isNextSelected.value = true;
        }
    }
);

/**
 * when the badge-detail is closed, we need to reset the selected badge,
 * otherwise we can't re-open the same badge
 *
 * @param { number } sequenceIndex - the sequence index of the modal, with 1 being the curriculum browser
 */
function onOpenPrevious(sequenceIndex) {
    if (sequenceIndex === 1) {
        modalStore.setSelectedBadge(null);
    }
}

/**
 * a code has been selected in the detail modal
 *
 * @param { object } node - the selected node
 */
function onCodeSelected(node) {
    selectedNodeDetails.value = node;
    isNextSelected.value = true;
}

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
        size="large"
        :is-close-disabled="false"
        :close-on-background-click="false"
        :is-open="modalStore.isOpen"
        :is-next-selected="isNextSelected"
        @next-opened="isNextSelected = false"
        @previous-opened="onOpenPrevious"
        @closed="onModalClosed"
    >
        <template #sequence-1>
            <cs-modal-header />
            <cs-modal-nodes />
            <cs-modal-footer />
        </template>
        <template #sequence-2>
            <badge-detail
                v-if="modalStore.selectedBadge"
                :badge="modalStore.selectedBadge"
                @code-selected="onCodeSelected"
            />
        </template>
        <template #sequence-3>
            <node-details :node="selectedNodeDetails" />
        </template>
    </sequential-reveal-modal>
</template>

<style scoped></style>
