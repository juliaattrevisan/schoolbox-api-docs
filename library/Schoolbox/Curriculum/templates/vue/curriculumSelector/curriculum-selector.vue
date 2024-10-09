<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To select Curriculum Nodes to assign to something like a Due Work
 */

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
     * set to true if curriculum nodes with blank codes need to be selectable
     */
    includeWithoutCode: {
        type: Boolean,
        required: false,
        default: false,
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
     * an 'original' selection, saved by the user,
     * currently used within the Badges filters, so when the `Reset to defaults`
     * button is selected, the default node selection is reapplied.
     */
    selectedOriginal: {
        type: Array,
        required: false,
        default: [],
    },
    /**
     * the default selection relevant to the current user role,
     * currently used within the Badges filters, so when the `Clear all`
     * button is selected, the original node selection is reapplied.
     */
    selectedDefault: {
        type: Array,
        required: false,
        default: [],
    },
    /**
     * so can only select a single node
     */
    isSingleSelect: {
        type: Boolean,
        required: false,
        default: false,
    },
    /**
     * can select any nodes, not just those that have a code
     */
    isSelectAll: {
        type: Boolean,
        required: false,
        default: false,
    },
    /**
     * is able to select children of an already selected node
     */
    canSelectChildren: {
        type: Boolean,
        required: false,
        default: true,
    },
    /**
     * the id of the search input element
     */
    inputId: {
        type: String,
        required: false,
        default: "",
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
    initResizeObserver();
});

/**
 * Any changes to the element size are tracked by ResizeObserver so can update dimensions
 */
function initResizeObserver() {
    const resizeObserver = new ResizeObserver(() => checkContainerWidth());
    resizeObserver.observe(curriculumSelectorEl.value);
}

/**
 * to track the width of the container the curriculum selector is in,
 * so we can update the design when appropriate
 */
async function checkContainerWidth() {
    await window.Vue.nextTick();
    store.updateContainerWidth(curriculumSelectorEl.value.offsetWidth);
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
        <cs-search-input
            :curriculum-selector-el="curriculumSelectorEl"
            :is-single-select="props.isSingleSelect"
            :input-id="props.inputId"
        />
        <cs-search-results :is-single-select="props.isSingleSelect" />
    </div>
    <cs-modal v-if="store.hasLoadedOk" :is-single-select="props.isSingleSelect" />
    <selected-node-modal :node="selectedNodesStore.activeNode" @closed="onSelectedNodeModalClosed" />
</template>
