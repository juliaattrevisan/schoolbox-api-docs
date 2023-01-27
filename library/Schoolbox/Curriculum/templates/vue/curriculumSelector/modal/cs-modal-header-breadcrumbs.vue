<script setup>
/**
 * To indicate progress through the node tree and to be able to
 * move backwards through the tree
 */

import { shortenLength } from "@js/util/stringUtils";
import { useModalActiveNodesStore } from "../store/csModalActiveNodesStore";

const modalActiveNodesStore = useModalActiveNodesStore();
const maxNameLength = 100;

/**
 * to display the just selected node
 *
 * @param { number }
 * @param { number }
 */
function onSelected(depth, id) {
    modalActiveNodesStore.setSelected(depth, parseInt(id));
}
</script>

<template>
    <TransitionGroup name="list" class="breadcrumb" tag="ul">
        <li v-for="breadcrumb in modalActiveNodesStore.breadcrumbs" :key="breadcrumb.id">
            <a
                :href="`/curriculum/#${breadcrumb.id}`"
                :title="`View ${breadcrumb.name}`"
                @click.prevent="onSelected(breadcrumb.depth, breadcrumb.id)"
                @keyup.enter="onSelected(breadcrumb.depth, breadcrumb.id)"
            >
                {{ shortenLength(breadcrumb.name, maxNameLength) }}
            </a>
        </li>
    </TransitionGroup>
</template>

<style lang="scss" scoped>
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
