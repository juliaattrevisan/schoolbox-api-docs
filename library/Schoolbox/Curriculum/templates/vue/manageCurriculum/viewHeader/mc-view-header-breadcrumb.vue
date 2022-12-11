<script setup lang="ts">

/**
 * To indicate progress through the node tree and to be able to
 * move backwards through the tree
 */

import { shortenLength } from '@js/util/stringUtils';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';
import { Breadcrumb } from '@schoolboxCurriculum/types/Breadcrumb';

interface Props {
    breadcrumb: Breadcrumb; // the relevant breadcrumb
    isDisabled: boolean; // to disable certain events when editing is taking place
}

const props = defineProps<Props>();
const maxNameLength = 50;
const viewNodesStore = useViewNodesStore();

/**
 * to display the just selected node
 */
function onSelected () {
    viewNodesStore.setSelected(props.breadcrumb.depth, props.breadcrumb.id);
}

</script>

<template>
    <li>
        <span v-if="props.isDisabled">
            {{ shortenLength(props.breadcrumb.name as string, maxNameLength) }}
        </span>
        <a v-else
            :href="`/curricululm/${props.breadcrumb.id}`"
            :title="`View ${props.breadcrumb.name}`"
            @click.prevent="onSelected()"
            @keyup.enter="onSelected()"
        >
            {{ shortenLength(props.breadcrumb.name as string, maxNameLength) }}
        </a>
    </li>
</template>