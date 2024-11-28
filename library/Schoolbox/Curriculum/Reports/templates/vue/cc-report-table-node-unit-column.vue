<script setup lang="ts">
/**
 * To display a a single unit cell in the curriculum coverage report,
 * with the ability to select the cell to reveal further details on the relevant usage
 */

import { useTemplateRef } from "vue";
import type {
    CurriculumCoverageNode,
    CourseWithCurriculumNodeUsage,
    UnitWithCurriculumNodeUsage,
} from "../js/types/curriculumCoverageTypes";
import { usePopOutStore } from "./store/ccPopOutStore";
import { getFilteredUnitColumnCount } from "./util/countUtils";

interface Props {
    node: CurriculumCoverageNode;
    course: CourseWithCurriculumNodeUsage;
    unit: UnitWithCurriculumNodeUsage;
    isExpanded: boolean; // whether the node is expanded
}
const props = defineProps<Props>();

const emit = defineEmits<{
    click: [course: CourseWithCurriculumNodeUsage, unit: UnitWithCurriculumNodeUsage, buttonEl: HTMLButtonElement];
}>();

const count = window.Vue.computed(() => {
    return getFilteredUnitColumnCount(props.node, props.course, props.unit, !props.isExpanded);
});
const isCountZero = window.Vue.computed(() => count.value === 0); // don't need a button when it's zero
const buttonId = window.Vue.computed(() => `usage-button-${props.course.id}-${props.unit.id}-${props.node.id}`);
const buttonEl = useTemplateRef<HTMLButtonElement>("buttonEl");
const popOutStore = usePopOutStore();

/**
 * Open the pop out
 */
function onClick(): void {
    emit("click", props.course, props.unit, buttonEl.value as HTMLButtonElement);
}
</script>

<template>
    <!-- padding will be on the button if count is not zeo -->
    <td :style="{ padding: isCountZero ? '0.625rem' : '0' }">
        <template v-if="isCountZero">
            {{ count }}
        </template>
        <button
            v-else
            :id="buttonId"
            ref="buttonEl"
            :style="{ background: buttonId === popOutStore.button?.id ? 'var(--accent-background)' : '' }"
            class="f-always-show"
            @click="onClick"
        >
            {{ count }}
        </button>
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
