<script setup lang="ts">
/**
 * To display a a single unit cell in the curriculum coverage report,
 * with the ability to select the cell to reveal further details on the relevant usage
 */

import type { Course } from "@schoolboxLibrary/types/Course";
import type { Unit } from "@schoolboxLibrary/types/Unit";
import type { CurriculumCoverageNode } from "../js/types/curriculumCoverageTypes";
import { usePopOutStore } from "./store/ccPopOutStore";

interface Props {
    course: Course;
    unit: Unit;
    node: CurriculumCoverageNode;
    count: number; // the usage number
}
const props = defineProps<Props>();

const isCountZero = window.Vue.computed(() => props.count === 0); // don't need a button when it's zero
const popOutStore = usePopOutStore();

/**
 * Open the pop out
 */
function onClick(): void {
    popOutStore.setActive(props.course, props.unit, props.node);
}
</script>

<template>
    <!-- padding will be on the button if count is not zeo -->
    <td :style="{ padding: isCountZero ? '0.625rem' : '0' }">
        <template v-if="isCountZero">
            {{ props.count }}
        </template>
        <button v-else @click="onClick">{{ props.count }}</button>
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
        background-color: var(--accent-background);
    }
}
</style>
