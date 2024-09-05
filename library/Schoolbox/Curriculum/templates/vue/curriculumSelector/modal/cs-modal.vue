<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * The modal that's used to help select curriculum
 */

import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import SequentialRevealModal from "@schoolboxVue/components/sequential-reveal-modal.vue";
import BadgeDetail from "@schoolboxBadge/vue/badgeDetail.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details";
import { useModalStore } from "../store/csModalStore";
import { useModalViewNodesStore } from "../store/csModalViewNodesStore";
import { useModalNodeUsageStore } from "../store/csModalNodeUsageStore";
import CsModalHeader from "./cs-modal-header.vue";
import CsModalNodes from "./cs-modal-nodes.vue";
import CsModalFooter from "./cs-modal-footer.vue";

const props = defineProps({
    /**
     * so can only select a single node
     */
    isSingleSelect: {
        type: Boolean,
        required: false,
        default: false,
    },
    /**
     * To open on a specific node, in situations where we wish
     * to restrict further navigation/selection to within a specific node
     */
    startingNodeId: {
        type: Number,
        required: false,
        default: UNSET_ID,
    },
});

const modalStore = useModalStore();
const modalViewNodesStore = useModalViewNodesStore();
const isNextSelected = window.Vue.ref(false); // to move to the next modal if a badge usage is selected
const modalNodeUsageStore = useModalNodeUsageStore();
const selectedNodeId = window.Vue.ref(null); // number|null

/**
 * Pass the single select prop to the store
 */
window.Vue.onMounted(() => {
    modalStore.isSingleSelect = props.isSingleSelect;
    void modalViewNodesStore.toStartingNodeId(props.startingNodeId);
});

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
    },
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
    selectedNodeId.value = node.id;
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
            <node-details v-if="selectedNodeId !== null" :node-id="selectedNodeId" />
        </template>
    </sequential-reveal-modal>
</template>

<style scoped></style>
