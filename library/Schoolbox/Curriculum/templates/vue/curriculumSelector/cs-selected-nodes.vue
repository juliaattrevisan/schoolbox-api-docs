<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To list all the currently selected Curriculum codes
 */

import { useStore } from "./store/curriculumSelectorStore";
import { useSelectedNodesStore } from "./store/csSelectedNodesStore";
import CsSelectedNode from "./cs-selected-node.vue";

const store = useStore();
const selectedNodesStore = useSelectedNodesStore();
const selectEl = window.Vue.ref(null);

/**
 * to listen for any clears of the selected codes from outside the Vue app
 */
window.Vue.onMounted(() => {
    document.addEventListener("SEARCH_SELECT__UPDATE_SELECTION", (event) => {
        if (selectEl.value.getAttribute("id") === event.detail.elementId) {
            //just in case there are multiple on the page
            const newSelection = JSON.parse(event.detail.selection);
            selectedNodesStore.update(newSelection);
        }
    });
});

/**
 * Add a comma separated string of the ids of the selected codes
 * for use in forms
 *
 * @returns { String }
 */
const ids = window.Vue.computed(() => {
    return selectedNodesStore.nodesIds.join(",");
});

/**
 * To manually dispatch a <select> change event when there's been a change in the selected nodes
 * As this is being handled by Vue rather than user input, the change event won't fire otherwise
 * except when the <select> is first displayed in the DOM.
 * We listen for this in library/Schoolbox/Filter/templates/panel.twig
 */
window.Vue.watch(
    () => selectedNodesStore.nodesIds,
    () => {
        window.Vue.nextTick(() => {
            //nextTick to ensure the dom has updated so we can access with the latest changes
            const event = new Event("change");
            selectEl.value.dispatchEvent(event);
        });
    },
);
</script>

<template>
    <div v-if="selectedNodesStore.hasNodes" class="selected-nodes-container" data-test-nodes-container>
        <cs-selected-node v-for="node in selectedNodesStore.nodes" :key="node.id" :node="node" data-test-node />
    </div>
    <div v-else-if="selectedNodesStore.hasNoNodes && store.isReadOnly" class="selected-nodes-container">
        <aside class="meta no-codes-note">No Curriculum Codes have been selected</aside>
    </div>
    <input :id="store.formValueName" :name="store.formValueName" type="hidden" tabindex="-1" :value="ids" />
    <select
        ref="selectEl"
        :id="`${store.formValueName}[]`"
        :name="`${store.formValueName}[]`"
        :data-selected-original="JSON.stringify(store.selectedOriginal)"
        :data-selected-default="JSON.stringify(store.selectedDefault)"
        tabindex="-1"
        multiple
        data-search-select
    >
        <option
            v-for="node in selectedNodesStore.nodes"
            :key="node.id"
            :value="node.id"
            :selected.attr="`selected`"
            :data-selected-object="JSON.stringify(selectedNodesStore.trimNode(node))"
        >
            {{ node.code }}
        </option>
    </select>
</template>

<style lang="scss" scoped>
.selected-nodes-container {
    background: #fff;
    border: 1px solid var(--form-border-color);
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: -1px; //to butt up against the search input
    max-height: 133px;
    min-height: 50px;
    overflow: auto;
    padding: 0.5rem;
}

.no-codes-note {
    margin-top: 0.35rem;
}

select[data-search-select] {
    display: none;
}
</style>
