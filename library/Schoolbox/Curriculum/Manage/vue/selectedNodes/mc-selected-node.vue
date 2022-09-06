<script setup>

/**
 * A selected Curriculum node, it can be deleted and
 * further details revealed via a modal
 */

import { useSelectedNodesStore } from './store/mcSelectedNodesStore';


const props = defineProps({
    /**
     * the selected curriculum node
     */
    node: {
        type: Object,
        required: true,
        default: () => {
            return {
                id: 0,
                code: '',
                name: '',
                breadcrumb: '',
            }
        }
    },
});

const selectedCodesStore = useSelectedNodesStore();

/**
 * To open a modal that shows further info on the selected code
 */
function onTitleSelected () {
    selectedCodesStore.setActiveNode(props.node);
}

/**
 * To remove from the selected list
 */
function onRemoveSelected () {
    selectedCodesStore.remove(props.node);
}

</script>

<template>
   <div class="selected-node">
        <button
            class="title-button"
            :title="`View ${props.node.name}`"
            @click.prevent="onTitleSelected"
            @keyup.enter="onTitleSelected"
            data-test-title-button>
            {{ props.node.name }}
        </button>
        <button
            class="remove-button icon-close"
            :title="`Remove ${props.node.name}`"
            @click.prevent="onRemoveSelected"
            @keyup.enter="onRemoveSelected"
            data-test-remove-button>
        </button>
    </div>
</template>

<style lang="scss" scoped>

.selected-node {
    background: var(--body-background);
    color: #000;
    display: flex;
    font-size: 0.8125rem;
    gap: 0.5rem;
    min-height: auto;
    padding: 5px 10px;
    text-align: left;
    transition: all 0.3s;
}

.title-button {
    background: var(--body-background);
    margin: 0;
    min-height: 0;
    padding: 0;
}

.remove-button {
    background: var(--body-background);
    border-radius: 1rem;
    flex: 0 0 1rem;
    height: 1rem;
    min-height: 0;
    margin-top: 3px;
    padding: 0;
}

</style>