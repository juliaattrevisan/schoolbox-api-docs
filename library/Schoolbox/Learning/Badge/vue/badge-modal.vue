<script setup>
/**
 * A general badge modal containing a sequential modal showing the badge details
 * then a selected curriculum code details
 */

import { ref } from "vue";
import SequentialRevealModal from "@schoolboxVue/components/sequential-reveal-modal.vue";
import NodeDetails from "@schoolboxCurriculum/vue/node-details.vue";
import { getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";
import BadgeDetail from "./badgeDetail.vue";

const props = defineProps({
    /**
     * whether the modal is open
     */
    isOpen: {
        type: Boolean,
        required: true,
        default: false,
    },
    /**
     * the badge to display
     */
    badge: {
        required: true,
    },
});

const emit = defineEmits(["closed"]);
const isNextSelected = ref(false);
const selectedNodeDetails = ref(getEmptyNode());

/**
 * a code has been selected in the detail modal
 *
 * @param { object } node - the selected node
 */
function onCodeSelected(node) {
    selectedNodeDetails.value = node;
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
            <node-details :node="selectedNodeDetails" />
        </template>
    </sequential-reveal-modal>
</template>

<style scoped></style>
