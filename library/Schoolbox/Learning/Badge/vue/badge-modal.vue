<script setup lang="ts">
/**
 * A general badge modal containing a sequential modal showing the badge details
 * then a selected curriculum code details
 */

import SequentialRevealModal from "@schoolboxVue/components/sequential-reveal-modal.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import type { Node, BadgeUsageObject } from "@schoolboxCurriculum/types/Node";
import type { Badge } from "@schoolboxBadge/templates/types/Badge";
import BadgeDetail from "./badgeDetail.vue";

interface Props {
    /**
     * whether the modal is open
     */
    isOpen: boolean;
    /**
     * the badge to display
     */
    badge: Badge | BadgeUsageObject | null;
}

const props = withDefaults(defineProps<Props>(), {
    isOpen: false,
});

const emit = defineEmits(["closed"]);
const isNextSelected = window.Vue.ref(false);
const selectedNodeId = window.Vue.ref<number | null>(null);

/**
 * a code has been selected in the detail modal
 * @param { Node } node - the selected node
 */
function onCodeSelected(node: Node) {
    selectedNodeId.value = node.id;
    isNextSelected.value = true;
}

/**
 * dispatch that the modal is closed
 */
function onModalClosed() {
    emit("closed");
}
</script>

<template>
    <sequential-reveal-modal
        size="medium"
        :is-open="props.isOpen"
        :is-next-selected="isNextSelected"
        @next-opened="isNextSelected = false"
        @closed="onModalClosed"
    >
        <template #sequence-1>
            <badge-detail v-if="props.badge" :badge="props.badge" @code-selected="onCodeSelected" />
        </template>
        <template #sequence-2>
            <node-details v-if="selectedNodeId !== null" :node-id="selectedNodeId" />
        </template>
    </sequential-reveal-modal>
</template>

<style scoped></style>
