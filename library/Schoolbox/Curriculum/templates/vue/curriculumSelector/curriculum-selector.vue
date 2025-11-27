<script setup lang="ts">
/**
 * To select Curriculum Nodes to assign to something like a Due Work
 */

import { useTemplateRef } from "vue";
import { getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { useStore } from "./store/curriculumSelectorStore";
import { useSelectedNodesStore } from "./store/csSelectedNodesStore";
import { useCurrentNodesStore } from "./store/csCurrentNodesStore";
import CsSearchInput from "./cs-search-input.vue";
import CsSelectedNodes from "./cs-selected-nodes.vue";
import CsSearchResults from "./cs-search-results.vue";
import CsModal from "./modal/cs-modal.vue";
import SelectedNodeModal from "../selected-node-modal.vue";

interface Props {
    dataEndpoint?: string; // the endpoint to retrieve all the group data from
    selectedNodes?: Node[]; // the nodes already previously selected
    isReadOnly?: boolean; // can only view the currently selected nodes, not edit them
    includeWithoutCode?: boolean; // set to true if curriculum nodes with blank codes need to be selectable
    formValueName?: string; // input name, to allow custom form structure
    selectedOriginal?: Node[]; // an 'original' selection, saved by the user
    selectedDefault?: Node[]; // the default selection relevant to the current user role
    isSingleSelect?: boolean; // so can only select a single node
    isSelectAll?: boolean; // can select any nodes, not just those that have a code
    canSelectChildren?: boolean; // is able to select children of an already selected node
    inputId?: string; // the id of the search input element
    includeChildren?: boolean; // When a node is selected, also select all its children
    includeDisabled?: boolean; // whether to display disabled nodes
    hasTabs?: boolean; // a split to show only curriculum linked to a parent unit / all curriculum
    parentFolderId?: number; // the folder id to use when loading current curriculum
}
const props = withDefaults(defineProps<Props>(), {
    dataEndpoint: "",
    selectedNodes: () => [],
    isReadOnly: true,
    includeWithoutCode: false,
    formValueName: "curriculumCodes[]",
    selectedOriginal: () => [],
    selectedDefault: () => [],
    isSingleSelect: false,
    isSelectAll: false,
    canSelectChildren: true,
    inputId: "",
    includeChildren: false,
    includeDisabled: false,
    hasTabs: false,
    parentFolderId: undefined,
});

const store = useStore();
const selectedNodesStore = useSelectedNodesStore();
const curriculumSelectorEl = useTemplateRef<HTMLDivElement | null>("curriculumSelectorEl");
const testId = window.Vue.ref<number>(0); // to add a id to target in tests when there are multiple selectors on the page

/**
 * to track the width of the container the curriculum selector is in,
 * so we can update the design when appropriate
 */
async function checkContainerWidth(): Promise<void> {
    await window.Vue.nextTick();
    store.updateContainerWidth((curriculumSelectorEl.value as HTMLDivElement).offsetWidth);
}

/**
 * Any changes to the element size are tracked by ResizeObserver so can update dimensions
 */
function initResizeObserver(): void {
    const resizeObserver = new ResizeObserver(() => void checkContainerWidth());
    resizeObserver.observe(curriculumSelectorEl.value as HTMLDivElement);
}

/**
 * close the modal, setting the activeNode to null so we can allow another selection
 */
function onSelectedNodeModalClosed(): void {
    selectedNodesStore.setActiveNode(getEmptyNode());
}

const currentNodesStore = useCurrentNodesStore();

/**
 * Start loading the curriculum data
 */
window.Vue.onMounted(() => {
    store.init(props);
    initResizeObserver();
    testId.value = document.querySelectorAll("[data-test^=curriculum-selector-]").length; // will increment for each selector on the page
});
</script>

<template>
    <div ref="curriculumSelectorEl" class="curriculum-selector" :data-test="`curriculum-selector-${testId}`">
        <cs-selected-nodes />
        <cs-search-input
            :curriculum-selector-el="curriculumSelectorEl"
            :is-single-select="props.isSingleSelect"
            :input-id="props.inputId"
        />
        <cs-search-results :is-single-select="props.isSingleSelect" />
    </div>
    <cs-modal
        v-if="store.hasLoadedOk"
        :is-single-select="props.isSingleSelect"
        :has-tabs="currentNodesStore.hasNodes"
        :test-id="testId"
    />
    <selected-node-modal :node="selectedNodesStore.activeNode" @closed="onSelectedNodeModalClosed" />
</template>
