<script setup lang="ts">
/**
 * To display a a single course total cell in the curriculum coverage report
 */

import type { CurriculumCoverageNode } from "../js/types/curriculumCoverageTypes";
import { getFilteredTotalCount } from "./util/countUtils";

interface Props {
    node: CurriculumCoverageNode;
    isExpanded: boolean; // whether the node is expanded
}
const props = defineProps<Props>();

const count = window.Vue.computed(() => {
    return getFilteredTotalCount(props.node, props.isExpanded);
});
</script>

<template>
    <td class="u-border-left">
        <b>{{ count }}</b>
    </td>
</template>

<style lang="scss" scoped></style>
