<script setup lang="ts">
/**
 * Display a single student's responses
 */

import { gsap } from "gsap";
import type { Student } from "@schoolboxLearnosity/js/types/Student";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";
import { useItemsStore } from "../store/hmItemsStore";
import { useStudentsStore } from "../store/hmStudentsStore";
import HmStudentsStudentItemMark from "./hm-students-student-item-mark.vue";
import HmStudentsStudentAttemptMark from "./hm-students-student-attempt-mark.vue";
import HmStudentsStudentItem from "./hm-students-student-item.vue";
import HmStudentsStudentAttemptComment from "./hm-students-student-attempt-comment.vue";

interface Props {
    student: Student; // the student to display
}
const props = defineProps<Props>();

const studentEl = window.Vue.ref<HTMLDivElement | null>(null); // the entire student container
const studentInnerContainerEl = window.Vue.ref<HTMLDivElement | null>(null); // the entire student container
const responseEl = window.Vue.ref<HTMLDivElement | null>(null); // the response container
const responseInnerContainerEl = window.Vue.ref<HTMLDivElement | null>(null); // the response container
const responseContentEl = window.Vue.ref<HTMLDivElement | null>(null); // the element to render the report into
const itemsStore = useItemsStore();
const studentsStore = useStudentsStore();

/**
 * To show/hide the entire student container, hiding if the student has been marked
 * and the option to show marked attempts is unchecked
 * @returns {string} - the height of the response container
 */
// const containerHeight = window.Vue.computed(() => {
//     return props.student.isMarked && !studentsStore.showMarkedAttempts ? "0fr" : "1fr";
// });

/**
 * To show/hide the entire student container, hiding if the student has been marked
 * and the option to show marked attempts is unchecked
 */
window.Vue.watch(
    () => studentsStore.showMarkedAttempts,
    () => {
        gsap.to(studentEl.value, {
            gridTemplateRows: props.student.isMarked && !studentsStore.showMarkedAttempts ? "0fr" : "1fr",
            duration: 0.3,
            onStart: () => {
                studentInnerContainerEl.value?.style.setProperty("overflow", "hidden");
            },
            onComplete: () => {
                // otherwise the ckeditor pop outs can be hidden
                if (studentsStore.showMarkedAttempts) {
                    studentInnerContainerEl.value?.style.setProperty("overflow", "visible");
                }
            },
        });
    }
);

/**
 * To show/hide the response when selected
 */
function onToggleSelected() {
    studentsStore.toggleStudent(props.student);
}

/**
 * Display the student now the linked report has been loaded
 */
window.Vue.watch(
    () => props.student.isReportLoaded,
    async () => {
        onToggleSelected();
        await window.Vue.nextTick();
    }
);

/**
 * To show/hide the response container
 */
window.Vue.watch(
    () => props.student.isExpanded,
    () => {
        gsap.to(responseEl.value, {
            gridTemplateRows: props.student.isExpanded ? "1fr" : "0fr",
            duration: 0.3,
            onStart: () => {
                responseInnerContainerEl.value?.style.setProperty("overflow", "hidden");
            },
            onComplete: () => {
                // otherwise the ckeditor pop outs can be hidden
                if (props.student.isExpanded) {
                    responseInnerContainerEl.value?.style.setProperty("overflow", "visible");
                }
            },
        });
    }
);

/**
 * Highlight that focus has shifted to this student to prompt marking
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
    <div ref="studentEl" class="student l-grid">
        <div ref="studentInnerContainerEl" class="student__inner-container">
            <div ref="studentEl" class="c-island l-flex-col l-pad-m l-gap-m" :data-has-focus="props.student.hasFocus">
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
                        <div class="c-img c-img--round c-img--s">
                            <div class="c-img__wrapper">
                                <img
                                    :src="`/portrait.php?id=${props.student.id}&size=square32`"
                                    :alt="`Photo of ${props.student.name}`"
                                />
                            </div>
                        </div>
                        <h2>{{ props.student.name }}</h2>
                    </div>
                    <div>
                        <template v-if="props.student.isReportLoaded">
                            <hm-students-student-item-mark
                                v-for="item in props.student.items"
                                :key="item.reference"
                                :item="item"
                                :container="(responseContentEl as HTMLDivElement)"
                            />
                            <hm-students-student-attempt-mark
                                v-if="itemsStore.isAttemptComment"
                                :student="props.student"
                            />
                        </template>
                    </div>
                </div>
                <div ref="responseEl" class="response l-grid">
                    <div ref="responseInnerContainerEl" class="response__inner-container">
                        <div ref="responseContentEl" class="response__content">
                            <!-- this is the div Learnosity looks for to insert the response content into, it will be completely replaced -->
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
                            <hm-students-student-attempt-comment
                                v-if="itemsStore.isAttemptComment"
                                :student="props.student"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.student {
    &__inner-container {
        overflow: hidden;
    }
}
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
