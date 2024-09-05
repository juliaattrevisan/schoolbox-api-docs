<script setup lang="ts">
/**
 * To indicate progress through the node tree and to be able to
 * move backwards through the tree
 */

import { shortenLength } from "@js/util/stringUtils";
import { useViewNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/viewNodes/store/mcViewNodesStore";
import type { Breadcrumb } from "@schoolboxCurriculum/types/Breadcrumb";

interface Props {
    breadcrumb: Breadcrumb; // the relevant breadcrumb
    isDisabled: boolean; // to disable certain events when editing is taking place
    isLast: boolean; // to indicate the last breadcrumb
}

const props = defineProps<Props>();
const maxNameLength = 50;
const viewNodesStore = useViewNodesStore();

/**
 * to display the just selected node
 */
function onSelected(): void {
    viewNodesStore.setSelected(props.breadcrumb.depth as number, props.breadcrumb.id);
}
</script>

<template>
    <li :class="{ active: props.isLast }">
        <span v-if="props.isDisabled || props.isLast">
            {{ shortenLength(props.breadcrumb.name as string, maxNameLength) }}
        </span>
        <a
            v-else
            :href="`/curriculum/#${props.breadcrumb.id}`"
            :title="`View ${props.breadcrumb.name}`"
            @click.prevent="onSelected()"
        >
            {{ shortenLength(props.breadcrumb.name as string, maxNameLength) }}
        </a>
    </li>
</template>
