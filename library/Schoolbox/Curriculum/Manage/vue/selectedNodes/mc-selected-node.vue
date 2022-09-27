<script setup>

/**
 * A selected Curriculum node, it can be deleted and
 * further details revealed via a modal
 */

import { computed } from 'vue';
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
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = computed(() => {
    if (props.node.code && props.node.code !== '') {
        return `[${props.node.code}]`;
    } else {
        return props.node.name;
    }
});

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
   <div
        class="selected-node"
        :data-enabled="props.node.enabled"
    >
        <button
            class="title-button"
            :title="`View ${props.node.name}`"
            @click.prevent="onTitleSelected"
            @keyup.enter="onTitleSelected"
            data-test-title-button>
            {{ title }}
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
    background: none;
    margin: 0;
    min-height: 0;
    padding: 0;
}

.remove-button {
    background: none;
    border-radius: 1rem;
    flex: 0 0 1rem;
    height: 1rem;
    min-height: 0;
    margin-top: 3px;
    padding: 0;
}

@import '../style/mcStyle';

</style>