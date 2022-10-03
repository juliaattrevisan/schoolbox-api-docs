<script setup>

/**
 * To indicate progress through the node tree and to be able to
 * move backwards through the tree
 */

import { computed } from 'vue';
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
    isUpdatingOrAdding: {
        type: Boolean,
        required: true,
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
        <span v-if="props.isUpdatingOrAdding">
            {{ props.breadcrumb.name }}
        </span>
        <a v-else
            :href="`/curricululm/${props.breadcrumb.id}`"
            :title="`View ${props.breadcrumb.name}`"
            @click.prevent="onSelected()"
            @keyup.enter="onSelected()"
        >
            {{ props.breadcrumb.name }}
        </a>
    </li>
</template>