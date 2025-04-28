<script setup lang="ts">
/**
 * The modal that's used to help select curriculum
 */

import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import SequentialRevealModal from "@schoolboxVue/components/sequential-reveal-modal.vue";
import BadgeDetail from "@schoolboxBadge/vue/badgeDetail.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { useModalStore } from "../store/csModalStore";
import { useModalViewNodesStore } from "../store/csModalViewNodesStore";
import { useModalNodeUsageStore } from "../store/csModalNodeUsageStore";
import CsAllModalHeader from "./all/cs-all-modal-header.vue";
import CsAllModalNodes from "./all/cs-all-modal-nodes.vue";
import CsAllModalFooter from "./all/cs-all-modal-footer.vue";

interface Props {
    isSingleSelect?: boolean; // so can only select a single node
    startingNodeId?: number; // To open on a specific node, in situations where we wish to restrict further navigation/selection to within a specific node
    hasTabs?: boolean; // a split to show only curriculum linked to a parent unit / all curriculum
}
const props = withDefaults(defineProps<Props>(), {
    isSingleSelect: false,
    startingNodeId: UNSET_ID,
    hasTabs: false,
});

const modalStore = useModalStore();
const modalViewNodesStore = useModalViewNodesStore();
const isNextSelected = window.Vue.ref(false); // to move to the next modal if a badge usage is selected
const modalNodeUsageStore = useModalNodeUsageStore();
const selectedNodeId = window.Vue.ref<number | null>(null); // number|null

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
 * @param sequenceIndex - the sequence index of the modal, with 1 being the curriculum browser
 */
function onOpenPrevious(sequenceIndex: number): void {
    if (sequenceIndex === 1) {
        modalStore.setSelectedBadge(null);
    }
}

/**
 * a code has been selected in the detail modal
 * @param node - the selected node
 */
function onCodeSelected(node: Node): void {
    selectedNodeId.value = node.id;
    isNextSelected.value = true;
}

/**
 * the modal is closed, also closing the node usage if it's open
 */
function onModalClosed(): void {
    modalStore.closed();
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
            <div class="l-flex-col l-gap-m">
                <dl v-if="props.hasTabs" ref="tabsEl" class="tabs">
                    <dd>
                        <a>Current Curriculum</a>
                    </dd>
                    <dd>
                        <a>All Curriculum</a>
                    </dd>
                </dl>
                <div>
                    <cs-all-modal-header />
                    <cs-all-modal-nodes />
                    <cs-all-modal-footer />
                </div>
            </div>
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
