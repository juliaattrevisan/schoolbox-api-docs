<script setup lang="ts">
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import { getEmptyNode, UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";

const selectedNode = window.Vue.ref(getEmptyNode());
const modalEl = window.Vue.ref(null);

interface Props {
    selectedNodeId: number; // the assigned node to display
}

const props = withDefaults(defineProps<Props>(), {
    selectedNodeId: UNSET_ID,
});

/**
 * start searching once the search is active, watching the query string for input
 */
window.Vue.watch(
    () => props.selectedNodeId,
    () => {
        if (props.selectedNodeId !== UNSET_ID) {
            selectedNode.value = { id: props.selectedNodeId };
            modalEl.value.open();
        }
    },
);

/**
 * a node link has been selected, so open up the details modal,
 * passing in the node id to load within node-details
 * the id is the only property that's required, as the other
 * details will be loaded in
 * @param { Event } event - the click event
 */
function onNodeLinkSelected(event: Event) {
    event.preventDefault();
    const { href } = event.currentTarget as HTMLAnchorElement;
    const selectedNodeId = parseInt(href.substring(href.indexOf("#") + 1, href.length), 10);
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
    void window.Vue.nextTick(() => {
        findNodeLinks();
    });
});
</script>

<template>
    <reveal-modal ref="modalEl">
        <node-details :node="selectedNode" />
    </reveal-modal>
</template>
