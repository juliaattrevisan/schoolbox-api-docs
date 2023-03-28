<script setup lang="ts">
/**
 * To indicate progress through the node tree and to be able to
 * move backwards through the tree
 */

import { shortenLength } from "@js/util/stringUtils";
import { useModalViewNodesStore } from "../store/csModalViewNodesStore";
import type { Breadcrumb } from "@schoolboxCurriculum/types/Breadcrumb";

interface Props {
    breadcrumb: Breadcrumb; // the relevant breadcrumb
    isDisabled: boolean; // to disable certain events when editing is taking place
}

const props = defineProps<Props>();
const maxNameLength = 50;
const modalViewNodesStore = useModalViewNodesStore();

/**
 * to display the just selected node
 */
function onSelected() {
    modalViewNodesStore.setSelected(props.breadcrumb.depth as number, props.breadcrumb.id);
}
</script>

<template>
    <li>
        <span v-if="props.isDisabled">
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
