<script setup lang="ts">
/**
 * Displays the selected curriculum nodes assigned to a folder
 * The selected nodes is displayed in a tree structure, showing both the ancestors and descendants
 */

import { useStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import FnNode from "./fn-node.vue";
import FnDeleteNode from "./fn-delete-node.vue";

interface Props {
    folderId: number; // The folder id to display the nodes for
    componentInstanceId: number; // The component instance id to display the nodes on
}
const props = defineProps<Props>();

const store = useStore(props.folderId, props.componentInstanceId);
</script>

<template>
    <transition-group name="list" tag="ul" class="nodes l-flex-col">
        <fn-node
            v-for="node in store().filteredNodes"
            :key="node.id"
            :node="node"
            :folder-id="props.folderId"
            :component-instance-id="props.componentInstanceId"
        />
    </transition-group>
    <fn-delete-node :folder-id="props.folderId" :component-instance-id="props.componentInstanceId" />
</template>

<style scoped>
.nodes {
    /* move the spacing in a bit */
    margin-inline-start: var(--spacing-m);
    margin-block-end: 0;
    padding-inline-start: var(--spacing-m);
}
.list-enter-active,
.list-leave-active {
    transition: opacity 0.5s ease;
}
.list-enter-from,
.list-leave-to {
    opacity: 0;
}
</style>
