<script setup>

/**
 * To manage Curriculum nodes, being able to view the entire node tree and
 * CRUD individual nodes
 */

import { ref, onMounted } from 'vue';
import { useStore } from './store/manageCurriculumStore';
import { useNodesStore } from './store/mcNodesStore';
import McHeader from './mc-header.vue';
import McView from './mc-view.vue';
import McSelectedNodes from './selectedNodes/mc-selected-nodes.vue';
import McEnableNodes from './selectedNodes/mc-enable-nodes.vue';
import McDeleteNodes from './selectedNodes/mc-delete-nodes.vue';
import McActiveSelectedNodeModal from './selectedNodes/mc-active-selected-node-modal.vue';
import Toast from '@schoolboxVue/components/toast.vue';


const store = useStore();
const nodesStore = useNodesStore();
const toastEl = ref(null);


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
});

/**
 * Start loading the curriculum data
 */
onMounted(() => {
    store.init(props, toastEl);
});

/**
 *
 */
nodesStore.$onAction(({ name, onError }) => {
    if (name === 'loadId') {
        onError((error) => {
            console.warn(error.message);
            store.toastEl.activate();
            store.toastEl.timeout(error.message, false, 5000);
        });
    }
});

</script>

<template>
    <mc-header />
    <mc-view />
    <mc-selected-nodes />
    <mc-enable-nodes />
    <mc-delete-nodes />
    <mc-active-selected-node-modal />
    <toast ref="toastEl"></toast>
</template>

<style lang="scss" scoped>


</style>