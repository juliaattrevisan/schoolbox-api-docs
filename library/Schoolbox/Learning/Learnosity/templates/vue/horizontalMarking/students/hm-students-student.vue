<script setup lang="ts">
/**
 * Display a single student's responses
 */

import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";
import type { Student } from "../types/Student";
import HmStudentsStudentMark from "./hm-students-student-mark.vue";
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
            learnosityContainer.remove(); // remove the learnosity container as it's no longer neexed after the next tick, as everything needed has been moved out of it now
        }
    }
);
</script>

<template>
    <div ref="studentEl" class="c-island l-flex-col l-pad-m">
        <div class="l-flex-row l-flex--justify-space l-gap-m f-no-margins">
            <div class="l-flex-row l-flex--align-center l-gap-m">
                <button
                    v-if="props.student.isReportLoaded"
                    :class="`toggle-button l-flex l-flex--align-center l-flex--justify-center ${
                        props.student.isExpanded ? 'icon-up-arrow' : 'icon-down-arrow'
                    }`"
                    :disabled="!props.student.isReportLoaded"
                    @click.prevent="onToggleSelected"
                ></button>
                <span v-else class="loader"></span>
                <h2>{{ props.student.name }}</h2>
            </div>
            <div>
                <template v-if="props.student.isReportLoaded">
                    <hm-students-student-mark
                        v-for="(item, index) in itemsStore.items"
                        :key="item.id"
                        :item="item"
                        :index="index"
                        :container="(studentEl as HTMLDivElement)"
                    />
                </template>
            </div>
        </div>
        <div class="response l-grid">
            <div class="response__inner-container">
                <div :id="props.student.elementId"></div>
                <template v-if="props.student.isReportLoaded">
                    <hm-students-student-item
                        v-for="item in props.student.items"
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
    height: 2.5rem;
    width: 2.5rem;
}

.loader {
    height: 2.5rem;
    width: 2.5rem;
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
