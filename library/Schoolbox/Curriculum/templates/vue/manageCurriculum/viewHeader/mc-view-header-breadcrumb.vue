<script setup lang="ts">

/**
 * To indicate progress through the node tree and to be able to
 * move backwards through the tree
 */

import { shortenLength } from '@js/util/stringUtils';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';


const props = defineProps({
    /**
     * the relevant breadcrumb
     */
    breadcrumb: {
        type: Object,
        required: true,
    },
    /**
     * to disable certain events when editing is taking place
     */
    isDisabled: {
        type: Boolean,
        required: true,
    },
    /**
     * to shorten names that exceed a specified length
     */
    maxNameLength: {
        type: Number,
        required: false,
        default: 50,
    },
});

const viewNodesStore = useViewNodesStore();

/**
 * to display the just selected node
 */
function onSelected () {
    viewNodesStore.setSelected(props.breadcrumb.depth, parseInt(props.breadcrumb.id));
}

</script>

<template>
    <li>
        <span v-if="props.isDisabled">
            {{ shortenLength(props.breadcrumb.name, props.maxNameLength) }}
        </span>
        <a v-else
            :href="`/curricululm/${props.breadcrumb.id}`"
            :title="`View ${props.breadcrumb.name}`"
            @click.prevent="onSelected()"
            @keyup.enter="onSelected()"
        >
            {{ shortenLength(props.breadcrumb.name, props.maxNameLength) }}
        </a>
    </li>
</template>