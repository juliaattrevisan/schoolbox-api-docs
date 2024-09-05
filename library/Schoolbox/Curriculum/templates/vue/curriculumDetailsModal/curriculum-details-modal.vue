<script setup lang="ts">
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";

const activeSelectedNodeId = window.Vue.ref<number | null>(null);
const modalEl = window.Vue.ref<InstanceType<typeof RevealModal> | null>(null);

interface Props {
    selectedNodeId?: number; // the assigned node to display
}

const props = withDefaults(defineProps<Props>(), {
    selectedNodeId: UNSET_ID,
});

const emit = defineEmits<{
    closed: [];
}>();

/**
 * An alternative to findNodeLinks() is to pass the selectedNodeId prop
 * and changes will trigger the details modal to open
 */
window.Vue.watch(
    () => props.selectedNodeId,
    () => {
        if (props.selectedNodeId !== UNSET_ID) {
            activeSelectedNodeId.value = props.selectedNodeId;

            if (modalEl.value) {
                modalEl.value.open();
            }
        }
    },
);

/**
 * a node link has been selected, so open up the details modal,
 * passing in the node id to load within node-details
 * the id is the only property that's required, as the other
 * details will be loaded in
 * @param event - the click event
 */
function onNodeLinkSelected(event: Event): void {
    event.preventDefault();
    const { href } = event.currentTarget as HTMLAnchorElement;
    const selectedNodeId = parseInt(href.substring(href.indexOf("#") + 1, href.length), 10);
    activeSelectedNodeId.value = selectedNodeId;

    if (modalEl.value) {
        modalEl.value.open();
    }
}

/**
 * find any links on the page that should open the details modal
 */
function findNodeLinks(): void {
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

/**
 * The modal has been closed, so emit the closed event
 */
function onModalClosed(): void {
    emit("closed");
}
</script>

<template>
    <reveal-modal ref="modalEl" @closed="onModalClosed">
        <node-details v-if="activeSelectedNodeId !== null" :node-id="activeSelectedNodeId" />
    </reveal-modal>
</template>
