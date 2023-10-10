<script setup>
/**
 * The curriculum overview component that can be added to a home page
 * to display all or a single curriculum node (and all its children)
 *
 * @NOTE - currently we are only handling the opening of the details-modal when a link is selected
 * however this whole component is ripe for a re-do, so more could be added in the near future
 */

import RevealModal from "@schoolboxVue/components/reveal-modal";
import NodeDetails from "@schoolboxCurriculum/vue/node-details";
import { getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";

window.Vue.onMounted(() => {
    window.Vue.nextTick(() => {
        findNodeLinks();
    });
});

const selectedNode = window.Vue.ref(getEmptyNode());
const modalEl = window.Vue.ref(null);

/**
 * find any links on the page that should open the details modal
 */
function findNodeLinks() {
    const nodeLinks = document.querySelectorAll("[data-display-node]");
    nodeLinks.forEach((nodeLink) => {
        nodeLink.addEventListener("click", (event) => onNodeLinkSelected(event));
    });
}

/**
 * a node link has been selected, so open up the details modal,
 * passing in the node id to load within node-details
 * the id is the only property that's required, as the other
 * details will be loaded in
 */
function onNodeLinkSelected(event) {
    event.preventDefault();
    const href = event.currentTarget.href;
    const selectedNodeId = parseInt(href.substring(href.indexOf("#") + 1, href.length));
    selectedNode.value = { id: selectedNodeId };
    modalEl.value.open();
}
</script>

<template>
    <reveal-modal ref="modalEl">
        <node-details :node="selectedNode" />
    </reveal-modal>
</template>
