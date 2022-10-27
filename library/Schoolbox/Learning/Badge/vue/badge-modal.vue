<script setup>

/**
 * A general badge modal containing a sequential modal showing the badge details
 * then a selected curriculum code details
 */

import { ref } from 'vue';
import SequentialRevealModal from '@schoolboxVue/components/sequential-reveal-modal';
import BadgeDetail from './badgeDetail';
import NodeDetails from '@schoolboxCurriculum/vue/node-details';
import { getEmptyNode } from '@schoolboxCurriculum/js/nodeUtils';


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

const emit = defineEmits(['closed']);

const sequenceIndex = ref(1);
const selectedNodeDetails = ref(getEmptyNode());

/**
 * a code has been selected in the detail modal
 *
 * @pararm { object }
 */
function onCodeSelected (node) {
    selectedNodeDetails.value = node;
    sequenceIndex.value++;
}

/**
 * dispatch that the modal is closed
 */
function onModalClosed () {
    emit('closed');
}

</script>

<template>
   <sequential-reveal-modal
        size="medium"
        :isOpen="isOpen"
        :sequence-index="sequenceIndex"
        @closed="onModalClosed"
    >
        <template v-slot:sequence-1>
            <badge-detail
                v-if="badge"
                :badge="badge"
                @code-selected="onCodeSelected"
            />
        </template>
        <template v-slot:sequence-2>
            <node-details
                :node="selectedNodeDetails"
            />
        </template>
    </sequential-reveal-modal>
</template>

<style scoped>

</style>