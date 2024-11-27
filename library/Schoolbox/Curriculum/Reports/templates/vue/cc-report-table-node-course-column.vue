<script setup lang="ts">
/**
 * To display a a single course cell in the curriculum coverage report,
 * with the ability to select the cell to reveal further details on the relevant usage
 */

import { useTemplateRef } from "vue";
import type { Course } from "@schoolboxLibrary/types/Course";
import type { CurriculumCoverageNode } from "../js/types/curriculumCoverageTypes";
import { usePopOutStore } from "./store/ccPopOutStore";
import { getCourseColumnCount, getFilteredCourseColumnCount } from "./util/countUtils";

interface Props {
    node: CurriculumCoverageNode;
    course: Course;
    isExpanded: boolean; // whether the node is expanded
}
const props = defineProps<Props>();

const emit = defineEmits<{
    click: [course: Course, buttonEl: HTMLButtonElement];
}>();

const hasNoUsages = window.Vue.ref(getCourseColumnCount(props.node, props.course, true) === 0); // no usage for the unit
const hasNoUsagesWithChildren = window.Vue.ref(getCourseColumnCount(props.node, props.course, false) === 0); // no usage for the unit and its children (if any)

const count = window.Vue.computed(() => {
    // to avoid unnecessary calculations when there are no usages, we return as early as possible
    if (hasNoUsages.value && hasNoUsagesWithChildren.value) {
        return 0;
    }
    return getFilteredCourseColumnCount(props.node, props.course, props.isExpanded);
});
const isCountZero = window.Vue.computed(() => count.value === 0); // don't need a button when it's zero
const buttonId = window.Vue.computed(() => `usage-button-${props.course.id}-${props.node.id}`);
const buttonEl = useTemplateRef<HTMLButtonElement>("buttonEl");
const popOutStore = usePopOutStore();

/**
 * Open the pop out
 */
function onClick(): void {
    emit("click", props.course, buttonEl.value as HTMLButtonElement);
}
</script>

<template>
    <!-- padding will be on the button if count is not zeo -->
    <td class="u-border-left" :style="{ padding: isCountZero ? '0.625rem' : '0' }">
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
