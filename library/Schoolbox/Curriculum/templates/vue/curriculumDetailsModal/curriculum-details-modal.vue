<script setup>
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import { getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";

const selectedNode = window.Vue.ref(getEmptyNode());
const modalEl = window.Vue.ref(null);

/**
 * a node link has been selected, so open up the details modal,
 * passing in the node id to load within node-details
 * the id is the only property that's required, as the other
 * details will be loaded in
 * @param { Event } event - the click event
 */
function onNodeLinkSelected(event) {
    event.preventDefault();
    const href = event.currentTarget.href;
    const selectedNodeId = parseInt(href.substring(href.indexOf("#") + 1, href.length));
    selectedNode.value = { id: selectedNodeId };
    modalEl.value.open();
}

/**
 * find any links on the page that should open the details modal
 */
function findNodeLinks() {
    const nodeLinks = document.querySelectorAll("[data-display-node]");
    nodeLinks.forEach((nodeLink) => {
        nodeLink.addEventListener("click", (event) => onNodeLinkSelected(event));
    });
}

window.Vue.onMounted(() => {
    window.Vue.nextTick(() => {
        findNodeLinks();
    });
});
</script>

<template>
    <reveal-modal ref="modalEl">
        <node-details :node="selectedNode" />
    </reveal-modal>
</template>
