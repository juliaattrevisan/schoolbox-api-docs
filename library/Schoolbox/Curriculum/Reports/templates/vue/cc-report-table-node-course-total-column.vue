<script setup lang="ts">
/**
 * To display a a single course total cell in the curriculum coverage report
 */

import type { Course } from "@schoolboxLibrary/types/Course";
import type { CurriculumCoverageNode } from "../js/types/curriculumCoverageTypes";
import { getFilteredCourseCount } from "./util/countUtils";

interface Props {
    node: CurriculumCoverageNode;
    course: Course;
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
