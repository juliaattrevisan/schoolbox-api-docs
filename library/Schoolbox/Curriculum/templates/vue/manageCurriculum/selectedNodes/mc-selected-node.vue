<script setup lang="ts">
/**
 * A selected Curriculum node, it can be deleted and
 * further details revealed via a modal
 */

import { shortenLength } from "@js/util/stringUtils";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { useSelectedNodesStore } from "./store/mcSelectedNodesStore";

interface Props {
    node: Node;
}

const props = defineProps<Props>();

const selectedNodesStore = useSelectedNodesStore();

/**
 * Add in the code if it's available otherwise just show the title
 */
const title = window.Vue.computed<string>(() => {
    if (props.node.code && props.node.code !== "") {
        return `${props.node.code}`;
    }

    return shortenLength(props.node.name, 50);
});

/**
 * To open a modal that shows further info on the selected code
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
    <div class="selected-node" :data-enabled="props.node.enabled">
        <button
            type="button"
            class="title-button"
            :title="`View ${props.node.name}`"
            data-test-title-button
            @click.prevent="onTitleSelected"
        >
            {{ title }}
        </button>
        <button
            type="button"
            class="remove-button icon-close"
            :title="`Remove ${props.node.name}`"
            data-test-remove-button
            @click.prevent="onRemoveSelected"
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

@import "../style/mcStyle";
</style>
