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
import CsModalCurrentNodes from "./current/cs-modal-current-nodes.vue";
import CsModalAllHeader from "./all/cs-modal-all-header.vue";
import CsModalAllNodes from "./all/cs-modal-all-nodes.vue";
import CsModalFooter from "./cs-modal-footer.vue";

interface Props {
    isSingleSelect?: boolean; // so can only select a single node
    startingNodeId?: number; // To open on a specific node, in situations where we wish to restrict further navigation/selection to within a specific node
    hasTabs?: boolean; // a split to show only curriculum linked to a parent unit / all curriculum
    testId?: number; // to add a modal id to target in tests when there are multiple modals on the page
}
const props = withDefaults(defineProps<Props>(), {
    isSingleSelect: false,
    startingNodeId: UNSET_ID,
    hasTabs: false,
    testId: 0,
});

const modalStore = useModalStore();
const modalViewNodesStore = useModalViewNodesStore();
const isNextSelected = window.Vue.ref(false); // to move to the next modal if a badge usage is selected

/**
 * Pass the single select prop to the store
 */
window.Vue.onMounted(() => {
    modalStore.isSingleSelect = props.isSingleSelect;
    void modalViewNodesStore.toStartingNodeId(props.startingNodeId);
});

// #region show badge usage details

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

// #endregion show badge usage details

// #region show code details

const selectedNodeId = window.Vue.ref<number | null>(null); // number|null

/**
 * a code has been selected in the detail modal
 * @param node - the selected node
 */
function onCodeSelected(node: Node): void {
    selectedNodeId.value = node.id;
    isNextSelected.value = true;
}

// #endregion show code details

const modalNodeUsageStore = useModalNodeUsageStore();

/**
 * the modal is closed, also closing the node usage if it's open
 */
function onModalClosed(): void {
    modalStore.closed();
    if (modalNodeUsageStore.isOpen) {
        modalNodeUsageStore.cancel();
    }
}

// #region tab switching

const current = "CURRENT";
const all = "ALL";
const currentTab = window.Vue.ref<string>(props.hasTabs ? current : all); // to track which tab is selected

/**
 * switch between the tabs
 * @param tab - the selected tab
 */
function onTabSelected(tab: string): void {
    currentTab.value = tab;
}

// #endregion tab switching
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
            <div class="l-flex-col l-gap-m" :data-test="`cs-modal-${props.testId}`">
                <h2 class="f-no-margins">Browse Curriculum</h2>
                <dl v-if="props.hasTabs" ref="tabsEl" class="tabs" data-test="cs-modal-tabs">
                    <dd :class="{ active: currentTab === current }">
                        <a data-test="cs-modal-current-tab-button" @click="onTabSelected(current)">Current</a>
                    </dd>
                    <dd :class="{ active: currentTab === all }">
                        <a data-test="cs-modal-all-tab-button" @click="onTabSelected(all)">All</a>
                    </dd>
                </dl>
                <cs-modal-current-nodes v-if="currentTab === current" />
                <div v-else>
                    <cs-modal-all-header />
                    <cs-modal-all-nodes />
                </div>
                <cs-modal-footer />
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
