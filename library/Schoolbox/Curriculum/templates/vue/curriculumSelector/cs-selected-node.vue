<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * A selected Curriculum node, it can be deleted and
 * further details revealed via a modal
 */

import { useStore } from "./store/curriculumSelectorStore";
import { useSelectedNodesStore } from "./store/csSelectedNodesStore";

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
                code: "",
                name: "",
            };
        },
    },
});

const store = useStore();
const selectedNodesStore = useSelectedNodesStore();

/**
 * Prevent rapid fire while loading the selected node
 *
 * @returns { boolean }
 */
const isDisabled = window.Vue.computed(() => selectedNodesStore.isLoading);

/**
 * To open a modal that shows further info on the selected node
 */
function onTitleSelected() {
    selectedNodesStore.setActiveNode(props.node);
}

/**
 * To remove from the selected list
 */
function onRemoveSelected() {
    selectedNodesStore.remove(props.node);
}
</script>

<template>
    <div class="selected-node">
        <button
            type="button"
            class="title-button"
            :title="`View ${props.node.name}`"
            :disabled="isDisabled"
            @click.prevent="onTitleSelected"
            data-test-title-button
        >
            {{ props.node.code === "" ? props.node.name : props.node.code }}
        </button>
        <button
            type="button"
            v-if="!store.isReadOnly"
            class="remove-button icon-close"
            :title="`Remove ${props.node.name}`"
            @click.prevent="onRemoveSelected"
            data-test-remove-button
        ></button>
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
    overflow: hidden;
    padding: 5px 10px;
    text-align: left;
    transition: all 0.3s;
    white-space: nowrap;
}

.title-button {
    background: var(--body-background);
    margin: 0;
    min-height: 0;
    padding: 0;
    text-overflow: ellipsis;
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
