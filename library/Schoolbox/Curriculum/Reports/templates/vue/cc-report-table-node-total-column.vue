<script setup lang="ts">
/**
 * To display a single total cell in the curriculum coverage report
 */

import type { CurriculumCoverageNode } from "../js/types/curriculumCoverageTypes";
import { getFilteredTotalCount } from "./util/countUtils";

interface Props {
    node: CurriculumCoverageNode;
    isExpanded: boolean; // whether the node is expanded
}
const props = defineProps<Props>();

const emit = defineEmits<{
    click: [];
}>();

const count = window.Vue.computed(() => {
    return getFilteredTotalCount(props.node, !props.isExpanded);
});

const canExpand = window.Vue.computed(() => props.node.children && !props.isExpanded);
</script>

<template>
    <td class="u-border-left" :style="{ padding: canExpand ? '0' : '0.625rem' }">
        <button v-if="canExpand" class="f-always-show" @click="emit('click')">
            <b>{{ count }}</b>
        </button>
        <template v-else>
            <b>{{ count }}</b>
        </template>
    </td>
</template>

<style lang="scss" scoped>
td {
    position: relative;
}

button {
    background: none;
    display: flex; // to align the text to the top left
    padding: 0.625rem;
    position: absolute; // make the button fill the entire td cell
    margin: 0;
    height: 100%;
    text-align: left;
    width: 100%;
    &:hover {
        background: var(--accent-background);
    }
}
</style>
