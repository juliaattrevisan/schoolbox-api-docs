<script setup lang="ts">
/**
 * The modal header, containing the title, select list and search input
 */

import { useModalNodeUsageStore } from "../store/csModalNodeUsageStore";
import { useNodesStore } from "../store/csNodesStore";
import CsModalHeaderBreadcrumbs from "./cs-modal-header-breadcrumbs.vue";
import CsModalHeaderSearchInput from "./cs-modal-header-search-input.vue";

const modalNodeUsageStore = useModalNodeUsageStore();
const nodesStore = useNodesStore();

const props = defineProps({
    /**
     * set to true if curriculum nodes with blank codes need to be selectable
     */
    includeWithoutCode: {
        type: Boolean,
        required: false,
        default: false,
    },
});

/**
 * Can't select the breadcrumbs or search input while updating or adding a node
 * or while nodes are being loaded
 * @returns {boolean} - whether the input is disabled or not
 */
const isDisabled = window.Vue.computed(() => modalNodeUsageStore.isOpen || nodesStore.isLoading);
</script>

<template>
    <h2>Browse Curriculum</h2>
    <cs-modal-header-breadcrumbs :is-disabled="isDisabled" />
    <cs-modal-header-search-input :is-disabled="isDisabled" :include-without-code="props.includeWithoutCode" />
</template>

<style scoped></style>
