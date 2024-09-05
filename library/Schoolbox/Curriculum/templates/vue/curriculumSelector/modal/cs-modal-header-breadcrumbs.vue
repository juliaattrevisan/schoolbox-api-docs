<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To indicate progress through the node tree and to be able to
 * move backwards through the tree
 */

import { useModalViewNodesStore } from "../store/csModalViewNodesStore";
import CsModalHeaderBreadcrumb from "./cs-modal-header-breadcrumb.vue";

const props = defineProps({
    /**
     * to disable certain events when editing is taking place
     */
    isDisabled: {
        type: Boolean,
        required: true,
    },
});

const modalViewNodesStore = useModalViewNodesStore();
</script>

<template>
    <ul name="list" class="breadcrumb" tag="ul">
        <cs-modal-header-breadcrumb
            v-for="(breadcrumb, index) in modalViewNodesStore.breadcrumbs"
            :key="breadcrumb.id"
            :breadcrumb="breadcrumb"
            :is-disabled="props.isDisabled"
            :is-last="index === modalViewNodesStore.breadcrumbs.length - 1"
        />
    </ul>
</template>

<style lang="scss" scoped></style>
