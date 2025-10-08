<script setup lang="ts">
/**
 * The modal header, containing the title, select list and search input
 */

import { useModalNodeUsageStore } from "../../store/csModalNodeUsageStore";
import { useNodesStore } from "../../store/csAllNodesStore";
import CsModalAllHeaderBreadcrumbs from "./cs-modal-all-header-breadcrumbs.vue";
import CsModalAllHeaderSearchInput from "./cs-modal-all-header-search-input.vue";

const modalNodeUsageStore = useModalNodeUsageStore();
const nodesStore = useNodesStore();

/**
 * Can't select the breadcrumbs or search input while updating or adding a node
 * or while nodes are being loaded
 * @returns {boolean} - whether the input is disabled or not
 */
const isDisabled = window.Vue.computed(() => modalNodeUsageStore.isOpen || nodesStore.isLoading);
</script>

<template>
    <cs-modal-all-header-breadcrumbs :is-disabled="isDisabled" />
    <cs-modal-all-header-search-input :is-disabled="isDisabled" />
</template>

<style scoped></style>
