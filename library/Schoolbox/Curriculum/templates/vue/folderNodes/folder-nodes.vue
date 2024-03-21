<script setup lang="ts">
/**
 * Displays the selected curriculum nodes assigned to a folder
 * The selected nodes is displayed in a tree structure, showing both the ancestors and descendants
 */

import { useStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";
import FnNode from "./fn-node.vue";

interface Props {
    folderId: number; // The folder id to display the nodes for
}
const props = defineProps<Props>();

const store = useStore(props.folderId);
const depth = window.Vue.ref(0); // to increment each time we go down the node tree to visually indicate children
</script>

<template>
    <transition-group name="list" tag="div" class="l-flex-col l-pad-s--horiz l-pad-s--top">
        <fn-node v-for="node in store().nodes" :key="node.id" :node="node" :folder-id="props.folderId" :depth="depth" />
    </transition-group>
</template>

<style scoped>
.list-enter-active,
.list-leave-active {
    transition: opacity 0.5s ease;
}
.list-enter-from,
.list-leave-to {
    opacity: 0;
}
</style>
