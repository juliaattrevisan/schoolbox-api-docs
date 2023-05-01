<script setup lang="ts">
/**
 * To indicate progress through the node tree and to be able to
 * move backwards through the tree
 */

import { useViewNodesStore } from "../viewNodes/store/mcViewNodesStore";
import McViewHeaderBreadcrumb from "./mc-view-header-breadcrumb.vue";

const props = defineProps({
    /**
     * to disable certain events when editing is taking place
     */
    isDisabled: {
        type: Boolean,
        required: true,
    },
});

const viewNodesStore = useViewNodesStore();
</script>

<template>
    <div class="small-12 columns">
        <TransitionGroup name="list" class="breadcrumb" tag="ul">
            <mc-view-header-breadcrumb
                v-for="(breadcrumb, index) in viewNodesStore.breadcrumbs"
                :key="breadcrumb.id"
                :breadcrumb="breadcrumb"
                :is-disabled="props.isDisabled"
                :is-last="index === viewNodesStore.breadcrumbs.length - 1"
            />
        </TransitionGroup>
    </div>
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
