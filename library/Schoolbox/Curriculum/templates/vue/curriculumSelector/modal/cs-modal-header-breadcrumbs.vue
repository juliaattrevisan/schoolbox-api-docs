<script setup>
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
    <TransitionGroup name="list" class="breadcrumb" tag="ul">
        <cs-modal-header-breadcrumb
            v-for="(breadcrumb, index) in modalViewNodesStore.breadcrumbs"
            :key="breadcrumb.id"
            :breadcrumb="breadcrumb"
            :is-disabled="props.isDisabled"
            :is-last="index === modalViewNodesStore.breadcrumbs.length - 1"
        />
    </TransitionGroup>
</template>

<style lang="scss" scoped>
//animate on/off the breadcrumbs, sliding in when added, sliding out when removed
.list-enter-active,
.list-leave-active {
    transition: all 0.3s ease;
}
.list-enter-from {
    opacity: 0;
    transform: translateX(-10px);
}
.list-leave-to {
    opacity: 0;
    transform: translateX(10px);
}
</style>
