<script setup lang="ts">
/**
 * To display a a single course total cell in the curriculum coverage report
 */

import type { Course } from "@schoolboxLibrary/types/Course";
import type { CurriculumCoverageNode } from "../js/types/curriculumCoverageTypes";
import { getCourseCount, getFilteredCourseCount } from "./util/countUtils";

interface Props {
    node: CurriculumCoverageNode;
    course: Course;
    isExpanded: boolean; // whether the node is expanded
}
const props = defineProps<Props>();

const hasNoUsages = window.Vue.ref(getCourseCount(props.node, props.course, true) === 0); // no usage for the unit
const hasNoUsagesWithChildren = window.Vue.ref(getCourseCount(props.node, props.course, false) === 0); // no usage for the unit and its children (if any)

const count = window.Vue.computed(() => {
    // to avoid unnecessary calculations when there are no usages, we return as early as possible
    if (hasNoUsages.value && hasNoUsagesWithChildren.value) {
        return 0;
    }
    return getFilteredCourseCount(props.node, props.course, props.isExpanded);
});
</script>

<template>
    <td>
        {{ count }}
    </td>
</template>

<style lang="scss" scoped></style>
