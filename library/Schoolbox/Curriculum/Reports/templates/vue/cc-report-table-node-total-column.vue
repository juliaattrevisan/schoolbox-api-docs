<script setup lang="ts">
/**
 * To display a a single course total cell in the curriculum coverage report
 */

import type { CurriculumCoverageNode } from "../js/types/curriculumCoverageTypes";
import { getTotalCount, getFilteredTotalCount } from "./util/countUtils";

interface Props {
    node: CurriculumCoverageNode;
    isExpanded: boolean; // whether the node is expanded
}
const props = defineProps<Props>();

const hasNoUsages = window.Vue.ref(getTotalCount(props.node, true) === 0); // no usage for the unit
const hasNoUsagesWithChildren = window.Vue.ref(getTotalCount(props.node, false) === 0); // no usage for the unit and its children (if any)

const count = window.Vue.computed(() => {
    // to avoid unnecessary calculations when there are no usages, we return as early as possible
    if (hasNoUsages.value && hasNoUsagesWithChildren.value) {
        return 0;
    }
    return getFilteredTotalCount(props.node, props.isExpanded);
});
</script>

<template>
    <td class="u-border-left">
        {{ count }}
    </td>
</template>

<style lang="scss" scoped></style>
