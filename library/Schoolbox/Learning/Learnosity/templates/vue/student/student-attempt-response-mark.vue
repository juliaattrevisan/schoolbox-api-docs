<script setup lang="ts">
/**
 * To provide a final mark response for a student
 */

import type { Student } from "@schoolboxLearnosity/js/types/Student";

interface Props {
    student: Student; // the student to display
    isReadOnly: boolean; // whether the question is readonly, we don't need to init the ckeditor if it's readonly
}
const props = defineProps<Props>();

const emit = defineEmits<{
    updatedMark: [mark: string];
}>();

const gradeEl = window.Vue.ref<HTMLDivElement | null>(null);

/**
 * Here we retrieve the current mark by observing the input field
 * As the input and validation are handled outside of Vue,
 * we need to observe the input field to update the mark
 * @param {HTMLElement} gradeContainer - the container to get the mark from
 */
function getMark(gradeContainer: HTMLElement) {
    const input = gradeContainer.querySelector("input");
    if (input !== null) {
        emit("updatedMark", input.value);
        // we need to observe the input value to update the mark when it changes
        const observer = new MutationObserver(() => {
            if (input.getAttribute("aria-invalid") === "true") {
                // check if it's a valid update
                return;
            }
            emit("updatedMark", input.value); // and if so update the mark
        });
        observer.observe(input, { attributeFilter: ["value", "aria-invalid"] });
    }
}

/**
 * The grade field utilises a considerable pre-existing twig/JS structure that we can't easily replicate in Vue,
 * see for example: library/Schoolbox/Learning/MarkScheme/templates/TotalMarkFractional.twig.
 * Rather than trying to rewrite the whole thing for a Vue context, we just store the grade field outside of Vue,
 * and then append it into the Vue component when it's mounted.
 */
function getGradeContainer() {
    const gradeContainer = document.getElementById(`grade-container-${props.student.id}`);
    if (gradeContainer !== null) {
        gradeEl.value?.append(gradeContainer);
        gradeContainer.classList.remove("hide"); // now remove the hide class to show the grade
        getMark(gradeContainer);
    }
}

/**
 * To add the grade field in
 */
window.Vue.onMounted(async () => {
    await window.Vue.nextTick();
    getGradeContainer();
});
</script>

<template>
    <div ref="gradeEl"></div>
</template>

<style scoped></style>
