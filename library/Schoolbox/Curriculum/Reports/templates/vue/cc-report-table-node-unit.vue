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

/**
 * Open the unit details pop out
 */
function onClick(): void {
    console.warn("click");
}
</script>

<template>
    <td>
        <button @click="onClick">{{ props.count }}</button>
    </td>
</template>

<style lang="scss" scoped>
td {
    padding: 0; /* apply padding to the button  instead */
    position: relative;
}

button {
    background: none;
    display: flex;
    padding: 0.625rem;
    position: absolute;
    margin: 0;
    height: 100%;
    text-align: left;
    width: 100%;
    &:hover {
        background-color: var(--accent-background);
    }
}
</style>
