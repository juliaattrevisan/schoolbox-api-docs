<script setup lang="ts">
/**
 * The header containing the breadcrumbs and search input
 */

import { useNodeUsageStore } from "../activeNode/store/mcNodeUsageStore";
import { useUpdateNodeStore } from "../activeNode/store/mcUpdateNodeStore";
import { useAddSubsectionStore } from "../activeNode/store/mcAddSubsectionStore";
import { useNodesStore } from "../store/mcNodesStore";
import McViewHeaderBreadcrumbs from "./mc-view-header-breadcrumbs.vue";
import McViewHeaderSearchInput from "./mc-view-header-search-input.vue";

const nodeUsageStore = useNodeUsageStore();
const updateNodeStore = useUpdateNodeStore();
const addSubsectionStore = useAddSubsectionStore();
const nodesStore = useNodesStore();

/**
 * Can't select the breadcrumbs or search input while updating or adding a node
 * or while nodes are being loaded
 *
 * @returns { boolean }
 */
const isDisabled = window.Vue.computed(
    () => nodeUsageStore.isOpen || updateNodeStore.isOpen || addSubsectionStore.isOpen || nodesStore.isLoading,
);
</script>

<template>
    <mc-view-header-breadcrumbs :is-disabled="isDisabled" />
    <mc-view-header-search-input :is-disabled="isDisabled" />
</template>

<style scoped></style>
