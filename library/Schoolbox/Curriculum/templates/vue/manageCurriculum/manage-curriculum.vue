<script setup lang="ts">
/**
 * To manage Curriculum nodes, being able to view the entire node tree and
 * CRUD individual nodes
 */

import * as toast from "@schoolboxVue/components/toast/sbxToastEvent";
import { getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";
import { useStore } from "./store/manageCurriculumStore";
import { useNodesStore } from "./store/mcNodesStore";
import { useSelectedNodesStore } from "./selectedNodes/store/mcSelectedNodesStore";
import { useViewNodesStore } from "./viewNodes/store/mcViewNodesStore";
import McHeader from "./mc-header.vue";
import McView from "./mc-view.vue";
import McSelectedNodes from "./selectedNodes/mc-selected-nodes.vue";
import McEnableNodes from "./selectedNodes/mc-enable-nodes.vue";
import McDeleteNodes from "./selectedNodes/mc-delete-nodes.vue";
import SelectedNodeModal from "../selected-node-modal.vue";

const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    dataEndpoint: {
        type: String,
        required: true,
        default: "",
    },
    /**
     * can only view the currently selected codes, not edit them
     */
    isReadOnly: {
        type: Boolean,
        required: true,
        default: true,
    },
    /**
     * number of nodes to retrieve from the endpoint
     */
    uiLimit: {
        type: Number,
        required: true,
        default: 0,
    },
    /**
     * route for the curriculum coverage report
     */
    routeCurriculumCoverage: String | null,
});

const store = useStore();
const selectedNodesStore = useSelectedNodesStore();

/**
 * Display an error if we cant find the node id that we jump to straight on page load
 */
function initErrorListener(): void {
    useNodesStore().$onAction(({ name, onError }) => {
        if (name === "loadId") {
            onError((error) => {
                console.error(error);
                const errorMessage = (error as Error).message;
                toast.activate(errorMessage);
                toast.timeout({ text: errorMessage, isSuccess: false });
            });
        }
    });
}

/**
 * So we can traverse the history when moving forward and back
 * through the node tree
 */
function initHistoryListener(): void {
    window.addEventListener("popstate", (event) => {
        void useViewNodesStore().updateOnPopState(event.state as { nodeId: number });
    });
}

/**
 * Start loading the curriculum data
 */
window.Vue.onMounted(async () => {
    await store.init(props.dataEndpoint, props.isReadOnly, props.uiLimit);
    if (store.hasId) {
        initErrorListener();
    }
    initHistoryListener();
});

/**
 * close the modal, setting the activeNode to null so we can allow another selection
 */
function onSelectedNodeModalClosed(): void {
    selectedNodesStore.setActiveNode(getEmptyNode());
}
</script>

<template>
    <mc-header :route-curriculum-coverage="routeCurriculumCoverage" />
    <mc-view />
    <mc-selected-nodes />
    <mc-enable-nodes />
    <mc-delete-nodes />
    <selected-node-modal :node="selectedNodesStore.activeNode" @closed="onSelectedNodeModalClosed" />
</template>
