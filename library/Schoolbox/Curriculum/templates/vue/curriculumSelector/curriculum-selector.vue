<script setup>
/**
 * To select Curriculum Nodes to assign to something like a Due Work
 */

import { ref, onMounted, nextTick } from "vue";
import { ResizeObserver } from "vue3-resize";
import "vue3-resize/dist/vue3-resize.css";
import { useStore } from "./store/curriculumSelectorStore";
import { useSelectedNodesStore } from "./store/csSelectedNodesStore";
import { getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";
import CsSearchInput from "./cs-search-input.vue";
import CsSelectedNodes from "./cs-selected-nodes.vue";
import CsSearchResults from "./cs-search-results.vue";
import CsModal from "./modal/cs-modal.vue";
import SelectedNodeModal from "../selected-node-modal.vue";

const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    dataEndpoint: {
        type: String,
        required: false,
        default: "",
    },
    /**
     * the nodes already previously selected
     */
    selectedNodes: {
        type: Array,
        required: false,
        default: [],
    },
    /**
     * can only view the currently selected nodes, not edit them
     */
    isReadOnly: {
        type: Boolean,
        required: false,
        default: true,
    },
    /**
     * input name, to allow custom form structure
     */
    formValueName: {
        type: String,
        required: false,
        default: "curriculumCodes[]",
    },
    /**
     * an 'original' selection, saved by the user
     */
    selectedOriginal: {
        type: Array,
        required: false,
        default: [],
    },
    /**
     * the default selection relevant to the current user role
     */
    selectedDefault: {
        type: Array,
        required: false,
        default: [],
    },
});

const store = useStore();
const selectedNodesStore = useSelectedNodesStore();
const curriculumSelectorEl = window.Vue.ref(null);

/**
 * Start loading the curriculum data
 */
window.Vue.onMounted(() => {
    store.init(props);
    checkContainerWidth();
});

/**
 * so can respond to dimenion changes if required
 */
async function checkContainerWidth() {
    await window.Vue.nextTick();
    store.updateContainerWidth(curriculumSelectorEl.value.offsetWidth);
}

/**
 * Any changes to the element size are tracked by ResizeObserver so can update dimensions
 */
function onContainerResize() {
    checkContainerWidth();
}

/**
 * close the modal, setting the activeNode to null so we can allow another selection
 */
function onSelectedNodeModalClosed() {
    selectedNodesStore.setActiveNode(getEmptyNode());
}
</script>

<template>
    <div ref="curriculumSelectorEl">
        <cs-selected-nodes />
        <cs-search-input :curriculum-selector-el="curriculumSelectorEl" />
        <cs-search-results />
        <resize-observer @notify="onContainerResize" />
    </div>
    <cs-modal v-if="store.hasLoadedOk" />
    <selected-node-modal :node="selectedNodesStore.activeNode" @closed="onSelectedNodeModalClosed" />
</template>
