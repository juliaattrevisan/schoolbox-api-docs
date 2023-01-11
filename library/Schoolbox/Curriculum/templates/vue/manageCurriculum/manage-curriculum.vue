<script setup lang="ts">

/**
 * To manage Curriculum nodes, being able to view the entire node tree and
 * CRUD individual nodes
 */

import { ref, onMounted } from 'vue';
import { useStore } from './store/manageCurriculumStore';
import { useNodesStore } from './store/mcNodesStore';
import { useSelectedNodesStore } from './selectedNodes/store/mcSelectedNodesStore';
import { getEmptyNode } from '@schoolboxCurriculum/js/nodeUtils';
import McHeader from './mc-header.vue';
import McView from './mc-view.vue';
import McSelectedNodes from './selectedNodes/mc-selected-nodes.vue';
import McEnableNodes from './selectedNodes/mc-enable-nodes.vue';
import McDeleteNodes from './selectedNodes/mc-delete-nodes.vue';
import SelectedNodeModal from '../selected-node-modal.vue';
import Toast from '@schoolboxVue/components/toast.vue';


const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    dataEndpoint: {
        type: String,
        required: true,
        default: '',
    },
    /**
     * can only view the currently selected codes, not edit them
     */
    isReadOnly: {
        type: Boolean,
        required: true,
        default: true,
    },
    /**
     * number of nodes to retrieve from the endpoint
     */
    uiLimit: {
        type: Number,
        required: true,
        default: 0
    }
});

const store = useStore();
const selectedNodesStore = useSelectedNodesStore();
const toastEl = ref<typeof Toast | null>(null);

/**
 * Start loading the curriculum data
 */
onMounted(() => {
    store.init(props.dataEndpoint, props.isReadOnly, props.uiLimit, toastEl);
    if (store.hasId) {
        initErrorListener();
    }
});

/**
 * Display an error if we cant find the node id that we jump to straight on page load
 */
function initErrorListener () {
    useNodesStore().$onAction(({ name, onError }) => {
        if (name === 'loadId') {
            onError((error) => {
                console.warn(error.message);
                store.toastEl?.activate();
                store.toastEl?.timeout(error.message, false, 5000);
            });
        }
    });
}

/**
 * close the modal, setting the activeNode to null so we can allow another selection
 */
function onSelectedNodeModalClosed () {
    selectedNodesStore.setActiveNode(getEmptyNode());
}

</script>

<template>
    <mc-header />
    <mc-view />
    <mc-selected-nodes />
    <mc-enable-nodes />
    <mc-delete-nodes />
    <selected-node-modal
        :node="selectedNodesStore.activeNode"
        @closed="onSelectedNodeModalClosed"
    />
    <toast ref="toastEl"></toast>
</template>
