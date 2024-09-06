<script setup lang="ts">
/**
 * To indicate progress through the node tree and to be able to
 * move backwards through the tree
 */

import { useModalViewNodesStore } from "../store/csModalViewNodesStore";
import CsModalHeaderBreadcrumb from "./cs-modal-header-breadcrumb.vue";

interface Props {
    isDisabled: boolean; // to disable certain events when editing is taking place
}
const props = defineProps<Props>();

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
