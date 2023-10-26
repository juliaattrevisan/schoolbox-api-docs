<script setup lang="ts">
/**
 * Display a single student's responses
 */

import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";
import type { Student } from "../types/Student";
import HmStudentsStudentMark from "./hm-students-student-mark.vue";
import HmStudentsStudentItem from "./hm-students-student-item.vue";
import HmStudentsStudentFinalReview from "./hm-students-student-final-review.vue";

interface Props {
    student: Student; // the student to display
}
const props = defineProps<Props>();

const studentEl = window.Vue.ref<HTMLDivElement | null>(null); //
const responseContentEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
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
 * Display the student item was the report has been loaded
 */
window.Vue.watch(
    () => props.student.isReportLoaded,
    () => {
        onToggleSelected();
    }
);

/**
 * Highlight that focus has shifted to this student after a previous item has been marked
 */
window.Vue.watch(
    () => props.student.hasFocus,
    () => {
        if (props.student.hasFocus) {
            studentEl.value?.scrollIntoView({ behavior: "smooth" });
        }
    }
);
</script>

<template>
    <div
        ref="studentEl"
        :class="`c-island l-flex-col l-pad-m ${$props.student.isExpanded ? 'l-gap-m' : ''}`"
        :data-has-focus="props.student.hasFocus"
    >
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
                        v-for="(item, markIndex) in itemsStore.items"
                        :key="item.id"
                        :item="item"
                        :index="markIndex"
                        :container="(responseContentEl as HTMLDivElement)"
                    />
                </template>
            </div>
        </div>
        <div class="response l-grid">
            <div class="response__inner-container">
                <div ref="responseContentEl" class="response__content">
                    <div :id="props.student.elementId"></div>
                </div>
                <template v-if="props.student.isReportLoaded">
                    <hm-students-student-item
                        v-for="item in props.student.items"
                        :key="item.reference"
                        :student="props.student"
                        :item="item"
                        :container="(responseContentEl as HTMLDivElement)"
                    />
                    <hm-students-student-final-review v-if="itemsStore.isFinalReview" :student="props.student" />
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
    grid-template-rows: v-bind(containerHeight);
    transition: grid-template-rows 300ms;
    &__inner-container {
        overflow: hidden;
    }
    &__content {
        // this contains the html from Learnosity, which has the response DOM elements that are extracted as we move through the items
        display: none;
    }
}

[data-has-focus="true"] {
    @extend .change-animation;
    animation-name: hasFocusAnimation;
}

.change-animation {
    animation-duration: 2s;
    animation-iteration-count: 1;
}

@keyframes hasFocusAnimation {
    0% {
        background-color: var(--content-ui-selected);
    }
    100% {
        background-color: var(--foreground-colour);
    }
}
</style>
