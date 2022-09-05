<script setup>

/**
 * To manage Curriculum nodes, being able to view the entire node tree and
 * CRUD individual nodes
 */

import { onMounted } from 'vue';
import { useStore } from './store/manageCurriculumStore';
import { useViewNodesStore } from './viewNodes/store/mcViewNodesStore';
import { useActiveNodeStore } from './activeNode/store/mcActiveNodeStore';
import McHeader from './mc-header.vue';
import McView from './mc-view.vue';
import McEnableNode from './activeNode/mc-enable-node.vue';
import McSelectedNodes from './mc-selected-nodes.vue';
import McDeleteSelectedNodes from './selectedNodes/mc-delete-selected-nodes.vue';


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
    checkForStorybook();
    const store = useStore();
    store.isReadOnly = props.isReadOnly;
    if (props.dataEndpoint !== '') { //only load when we need to
        store.load(props.dataEndpoint);
    }
});

/**
 * When switching between stories, we need to reset the data models
 * as they persist between the stories otherwise
 */
function checkForStorybook () {
    if (typeof STORYBOOK_ENV !== 'undefined') {
        useStore().$reset();
        useViewNodesStore().$reset();
        useActiveNodeStore().$reset();
    }
}

</script>

<template>
    <mc-header />
    <mc-view />
    <mc-enable-node />
    <mc-selected-nodes />
    <mc-delete-selected-nodes />
</template>

<style lang="scss" scoped>


</style>