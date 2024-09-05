<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * The modal header, containing the title, select list and search input
 */

import { useModalNodeUsageStore } from "../store/csModalNodeUsageStore";
import { useNodesStore } from "../store/csNodesStore";
import CsModalHeaderBreadcrumbs from "./cs-modal-header-breadcrumbs.vue";
import CsModalHeaderSearchInput from "./cs-modal-header-search-input.vue";

const modalNodeUsageStore = useModalNodeUsageStore();
const nodesStore = useNodesStore();

/**
 * Can't select the breadcrumbs or search input while updating or adding a node
 * or while nodes are being loaded
 *
 * @returns { boolean }
 */
const isDisabled = window.Vue.computed(() => modalNodeUsageStore.isOpen || nodesStore.isLoading);
</script>

<template>
    <h2>Browse Curriculum</h2>
    <cs-modal-header-breadcrumbs :is-disabled="isDisabled" />
    <cs-modal-header-search-input :is-disabled="isDisabled" />
</template>

<style scoped></style>
