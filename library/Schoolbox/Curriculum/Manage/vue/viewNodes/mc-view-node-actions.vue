<script setup>

/**
 * To edit an existing node or add a new subsection to a node
 */

import { useNodesStore } from '../store/mcNodesStore';
import { useAddSubsectionStore } from '../activeNode/store/mcAddSubsectionStore';
import { useUpdateNodeStore } from '../activeNode/store/mcUpdateNodeStore';


const props = defineProps({
    /**
     * the assigned node to display
     */
    node: {
        type: Object,
        required: true,
        default() {
            return {
                id: '0',
                name: '-',
                children: [],
            }
        }
    },
    /**
     * the already computed node title
     */
    title: {
        type: String,
        required: true,
    },
    /**
     * to disable certain events when updating the node
     */
    isUpdating: {
        type: Boolean,
        required: true,
    },
    /**
     * to disable certain events when adding a node
     */
    isAdding: {
        type: Boolean,
        required: true,
    },
    /**
     * to disable certain events when any editing is taking place
     */
    isUpdatingOrAdding: {
        type: Boolean,
        required: true,
    },
});

const addSubsectionStore = useAddSubsectionStore();
const updateNodeStore = useUpdateNodeStore();


/**
 * to open up or close the update modal
 */
function onAddSubsectionToggleSelected () {
    props.isAdding ? addSubsectionStore.cancel() : addSubsectionStore.prompt(props.node);
}

/**
 * to open up or close the update modal
 */
function onUpdateToggleSelected () {
    props.isUpdating ? updateNodeStore.cancel() : updateNodeStore.prompt(props.node);
}

</script>

<template>
    <button
        :class="`button-action-option ${ props.isUpdating ? 'icon-close' : 'icon-modify' }`"
        :title="`Update ${props.title}`"
        :disabled="props.isAdding"
        @click.prevent="onUpdateToggleSelected"
        @keyup.enter="onUpdateToggleSelected"
    >
        Update {{ props.title }}
    </button>
    <button
        :class="`button-action-option ${ props.isAdding ? 'icon-close' : 'icon-add' }`"
        :title="`Add subsection to ${props.title}`"
        :disabled="props.isUpdating"
        @click.prevent="onAddSubsectionToggleSelected"
        @keyup.enter="onAddSubsectionToggleSelected"
    >
        Add subsection to {{ props.title }}
    </button>
</template>

<style lang="scss" scoped>

@import './style/mcViewNode';

.button-action-option {
    @extend .button;
    color: var(--content-ui-foreground);
    font-size: 0rem;
    opacity: 0.7;
    padding: 0px 2px 0px 12px;
    &:hover {
        opacity: 1;
    }
}

</style>