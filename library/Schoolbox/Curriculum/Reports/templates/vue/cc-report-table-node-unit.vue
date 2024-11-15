<script setup lang="ts">
/**
 * To display a a single unit cell in the curriculum coverage report,
 * with the ability to select the cell to reveal further details on the relevant usage
 */

import type { CurriculumCoverageNode } from "../js/types/curriculumCoverageTypes";

interface Props {
    node: CurriculumCoverageNode;
    count: number; // the usage number
}
const props = defineProps<Props>();
</script>

<template>
    <td>{{ props.count }}</td>
</template>

<style scoped></style>
