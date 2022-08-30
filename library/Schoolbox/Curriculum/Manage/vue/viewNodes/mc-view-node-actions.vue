<script setup>

/**
 * To add, edit, delete or enable/disable a single node
 */

 import { computed } from 'vue';
import { useNodesStore, findById } from '../store/mcNodesStore';
import { useDeleteNodeStore } from '../activeNode/store/mcDeleteNodeStore';
import { useAddSubsectionStore } from '../activeNode/store/mcAddSubsectionStore';
import { useEnableNodeStore } from '../activeNode/store/mcEnableNodeStore';
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

const nodesStore = useNodesStore();
const deleteNodeStore = useDeleteNodeStore();
const addSubsectionStore = useAddSubsectionStore();
const enabledNodeStore = useEnableNodeStore();
const updateNodeStore = useUpdateNodeStore();


/**
 * to prompt the nodes removal
 */
function onDeleteSelected () {
    deleteNodeStore.prompt(props.node);
}

/**
 * to open up or close the update modal
 */
function onEnableToggleSelected () {
    props.node.enabled ? enabledNodeStore.prompt(props.node) : enabledNodeStore.enable(props.node);
}

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

/**
 *
 *
 * @returns { Boolean }
 */
const isParentEnabled = computed(() => {
    return true;
    if (props.node.parent === null) { //assumed if there's no parent, we are at the root level, and we'll always need to show the button in this case
        return true;
    } else {
        const parentNode = findById(nodesStore.nodes.children, props.node.parent?.id);
        return parentNode.enabled;
    }
});

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
    <!-- only show this when the parent is enabled -->
    <button
        v-if="isParentEnabled"
        :class="`button-action-option ${ props.node.enabled ? 'icon-view' : 'icon-view-hollow' }`"
        :title="`${ props.node.enabled ? 'Disable' : 'Enable' } node`"
        :disabled="props.isUpdatingOrAdding"
        @click.prevent="onEnableToggleSelected"
        @keyup.enter="onEnableToggleSelected"
    >
        Toggle visiblity of {{ props.title }}
    </button>
    <button
        class="button-action-option icon-delete"
        :title="`Delete ${props.title}`"
        :disabled="props.isUpdatingOrAdding"
        @click.prevent="onDeleteSelected"
        @keyup.enter="onDeleteSelected"
    >
        Delete {{ title }}
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