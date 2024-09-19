<script setup lang="ts">
/**
 * A single node row in the curriculum coverage report.
 */

interface Props {
    node: object; //
}
const props = defineProps<Props>();

function getUnitUsageCount(usageCounts) {
    return usageCounts.unit + usageCounts.activity + usageCounts.lesson + usageCounts.rubric;
}

function getCourseUsageCount(course) {
    let count = 0;
    course.units.forEach((unit) => {
        count += getUnitUsageCount(unit._usageCounts);
    });
    return count;
}

function getNodeUsageCount(node) {
    let count = 0;
    node.usage.courses.forEach((course) => {
        count += getCourseUsageCount(course);
    });
    return count;
}
</script>

<template>
    <tr>
        <th scope="row">{{ props.node.name }}</th>
        <td>{{ getNodeUsageCount(props.node) }}</td>
        <template v-for="course in props.node.usage.courses" :key="course.id">
            <td v-for="unit in course.units" :key="unit.id">
                {{ getUnitUsageCount(unit._usageCounts) }}
            </td>
            <td>{{ getCourseUsageCount(course) }}</td>
        </template>
    </tr>
    <template v-if="props.node.children && props.node.children.length > 0">
        <cc-report-node v-for="child in props.node.children" :key="child.id" :node="child" />
    </template>
</template>

<style scoped></style>
