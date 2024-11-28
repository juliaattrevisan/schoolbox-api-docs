<script setup lang="ts">
/**
 * To display a single course total cell in the curriculum coverage report
 */

import type { CurriculumCoverageNode, CourseWithCurriculumNodeUsage } from "../js/types/curriculumCoverageTypes";
import { getFilteredCourseCount } from "./util/countUtils";

interface Props {
    node: CurriculumCoverageNode;
    course: CourseWithCurriculumNodeUsage;
    isExpanded: boolean; // whether the node is expanded
}
const props = defineProps<Props>();

const count = window.Vue.computed(() => {
    return getFilteredCourseCount(props.node, props.course, props.isExpanded);
});
</script>

<template>
    <td>
        {{ count }}
    </td>
</template>

<style lang="scss" scoped></style>
