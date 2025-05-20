<script setup lang="ts">
/**
 * Displays the selected curriculum nodes assigned to a folder
 * The selected nodes is displayed in a tree structure, showing both the ancestors and descendants
 */

import { useStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import FnListNode from "./fn-list-node.vue";

interface Props {
    folderId: number; // The folder id to display the nodes for
    componentInstanceId: number; // The component instance id to display the nodes on
    isSmall?: boolean; // Whether the list should be displayed in a small format
}
const props = defineProps<Props>();

const store = useStore(props.folderId, props.componentInstanceId);
</script>

<template>
    <ul v-if="store().hasLoaded" class="nodes l-flex-col" data-test="fn-list-container">
        <fn-list-node
            v-for="node in store().filteredNodes"
            :key="node.id"
            :node="node"
            :folder-id="props.folderId"
            :component-instance-id="props.componentInstanceId"
            :is-small="props.isSmall"
        />
    </ul>
</template>

<style scoped>
.nodes {
    /* move the spacing in a bit */
    margin-inline-start: var(--spacing-m);
    margin-block-end: 0;
    padding-inline-start: var(--spacing-m);
}
.nodes li ul {
    margin-inline-start: var(--spacing-xs);
}
</style>
