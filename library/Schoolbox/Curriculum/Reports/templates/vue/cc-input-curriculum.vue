<script setup lang="ts">
/**
 * To search and select for curriculum in curriculum coverage report
 * note - This is a temporary solution until the curriculum-selector is updated
 * It's specifically for the curriculum coverage report for now as I want to limit
 * the scope of the changes
 */

import { watchIgnorable } from "@vueuse/core";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import type { SearchSelectItem } from "@schoolboxVue/composables/search/types/SearchSelectItem";
import { useStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/curriculumSelectorStore";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";
import { useModalStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csModalStore";
import { useNodeTitle } from "@schoolboxCurriculum/vue/composables/useNodeTitle";
import { getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";
import CsModal from "@schoolboxCurriculum/vue/curriculumSelector/modal/cs-modal.vue";
import SelectedNodeModal from "@schoolboxCurriculum/vue/selected-node-modal.vue";
import type { Node } from "@schoolboxCurriculum/types/Node";

interface Props {
    endpoint: string; // The curriculum api endpoint
    preselected: Node[]; // The nodes that have already been selected previously
    label?: string; // The label text
    labelId?: string; // The label id
    isRequired?: boolean; // Whether the input requires a value
    hasError?: boolean; // Whether the input has an error
    errorMessage?: string; // The error message to display
    isReadOnly?: boolean; // Whether the input is read only
    isSingleSelect?: boolean; // Whether only one curriculum node can be selected
}
const props = withDefaults(defineProps<Props>(), {
    label: "Select Curriculum",
    labelId: "curriculum",
    errorMessage: "Please select at least one curriculum outcome",
});

const store = useStore();
store.init({
    // just pass the props directly to the store for now, this would later be handled just as props with <curriculum-selector>
    dataEndpoint: props.endpoint,
    selectedNodes: [],
    isReadOnly: props.isReadOnly,
    includeWithoutCode: true,
    formValueName: "curriculumCodes[]",
    isSelectAll: true,
    canSelectChildren: false,
});
const selectedStore = useSelectedNodesStore();
selectedStore.add(props.preselected);
const modalStore = useModalStore();
const endpoint = `${props.endpoint}?enabled=1&limit=50&includeWithoutCode=1&query=`; // The endpoint to retrieve the curriculum data from
const results = window.Vue.ref<Node[]>([]);

/**
 * To inject the code / title of the node into the name field
 */
const { ignoreUpdates } = watchIgnorable(results, (newResults: Node[] | unknown[]) => {
    // ignore updates to avoid an infinite loop...
    ignoreUpdates(() => {
        if (newResults instanceof Array && newResults.length === 0) {
            return;
        }
        results.value = results.value.map((result) => {
            const { title } = useNodeTitle(result);
            return {
                ...result,
                id: result.id,
                name: title.value, // manipulate the name to display the code / title of the curriculum
            };
        });
    });
});

/**
 * To open up the selected node modal
 * @param node - The selected node
 */
function onNodeNameSelect(node: SearchSelectItem): void {
    selectedStore.setActiveNode(node as Node);
}

/**
 * close the modal, setting the activeNode to null so we can allow another selection
 */
function onSelectedNodeModalClosed(): void {
    selectedStore.setActiveNode(getEmptyNode());
}
</script>

<template>
    <search-select
        v-model:selected="selectedStore.nodes"
        v-model:results="results"
        :label="props.label"
        :label-id="props.labelId"
        :is-required="props.isRequired"
        :endpoint="endpoint"
        :has-error="props.hasError"
        :error-message="props.errorMessage"
        :is-read-only="props.isReadOnly"
        :allow-partial-matches="true"
        :can-select-item="true"
        :has-button="true"
        button-icon="icon-curriculum"
        @select="onNodeNameSelect"
        @button-select="modalStore.open"
    />
    <cs-modal v-if="store.hasLoadedOk" :is-single-select="props.isSingleSelect" />
    <selected-node-modal :node="selectedStore.activeNode" @closed="onSelectedNodeModalClosed" />
</template>

<style scoped></style>
