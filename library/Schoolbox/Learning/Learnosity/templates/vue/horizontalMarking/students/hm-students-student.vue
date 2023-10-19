<script setup lang="ts">
/**
 * Display a single student's responses
 */

import { hideAllChildren } from "@js/util/domUtils";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";
import type { Student } from "../types/Student";
import HmStudentsStudentItem from "./hm-students-student-item.vue";

interface Props {
    student: Student; // the student to display
}
const props = defineProps<Props>();

const studentEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
const itemsStore = useItemsStore();
const studentsStore = useStudentsStore();

/**
 * To show/hide the response when selected
 */
function onToggleSelected() {
    studentsStore.toggleStudent(props.student);
}

/**
 * To show/hide the response container
 * @returns {string} - the height of the response container
 */
const containerHeight = window.Vue.computed(() => {
    return props.student.isExpanded ? "1fr" : "0fr";
});

/**
 * When its loaded hide everything by default
 */
window.Vue.watch(
    () => props.student.isReportLoaded,
    async () => {
        if (props.student.isReportLoaded) {
            await window.Vue.nextTick(); // ensure the learnosity DOM is ready
            const learnosityContainer = studentEl.value?.querySelector(`#${props.student.elementId}`) as HTMLDivElement;
            // hideAllChildren(learnosityContainer);
        }
    }
);
</script>

<template>
    <div ref="studentEl" class="c-island l-flex-col l-pad-m">
        <div class="l-flex-row l-gap-m f-no-margins">
            <button
                v-if="props.student.isReportLoaded"
                :class="`toggle-button ${props.student.isExpanded ? 'icon-up-arrow' : 'icon-down-arrow'}`"
                :disabled="!props.student.isReportLoaded"
                @click.prevent="onToggleSelected"
            ></button>
            <span v-else class="loader"></span>
            <h2>{{ props.student.name }}</h2>
        </div>
        <div class="response l-grid">
            <div class="response__inner-container">
                <div :id="props.student.elementId"></div>
                <template v-if="props.student.isReportLoaded">
                    <hm-students-student-item
                        v-for="item in itemsStore.items"
                        :key="item.id"
                        :item="item"
                        :container="(studentEl as HTMLDivElement)"
                    />
                </template>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.toggle-button {
    background: none;
    color: var(--content-ui-foreground);
}
.response {
    background-color: var(--foreground-colour);
    grid-template-rows: v-bind(containerHeight);
    transition: grid-template-rows 300ms;
    &__inner-container {
        overflow: hidden;
    }
}
</style>
