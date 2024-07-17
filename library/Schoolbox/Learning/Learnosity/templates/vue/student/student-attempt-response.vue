<script setup lang="ts">
/**
 * To provide the opportunity for a final comment
 */

import { watchDebounced } from "@vueuse/core";
import { timeout } from "@js/util/jsUtils";
import type { Student } from "@schoolboxLearnosity/js/types/Student";

interface Props {
    student: Student; // the student to display
    isReadOnly: boolean; // whether the question is readonly, we don't need to init the ckeditor if it's readonly
}
const props = defineProps<Props>();

const gradeEl = window.Vue.ref<HTMLDivElement | null>(null);
const editorId = `final-review-comment-${props.student.elementId}`;
const comment = window.Vue.ref(props.student.attemptResponse.comment ?? "");
const isSaving = window.Vue.ref(false);
const hasSaved = window.Vue.ref(false);
const hasError = window.Vue.ref(false);

const emit = defineEmits<{
    updatedComment: [comment: string];
    updatedMark: [mark: string];
}>();

/**
 * To notify of the current save status
 * @returns {string} - the text to display
 */
const saveText = window.Vue.computed(() => {
    if (isSaving.value) {
        return "Saving...";
    }
    if (hasError.value) {
        return "Comment could not be saved";
    }
    return "Comment saved";
});

/**
 * To open and close the save status
 * @returns {string} - the grid-template-rows
 */
const saveContainerHeight = window.Vue.computed(() => {
    if (isSaving.value || hasSaved.value || hasError.value) {
        return "1fr";
    }
    return "0fr";
});

/**
 * To notify the user that they're leaving the page while data is being saved
 * @param {BeforeUnloadEvent} event - the event to prevent
 */
function beforeUnloadHandler(event: BeforeUnloadEvent) {
    event.preventDefault();
    event.returnValue = "";
}

/**
 * Save the comment if it's changed
 */
async function saveComment() {
    if (isSaving.value) {
        return;
    }
    if (comment.value === props.student.attemptResponse.comment) {
        return;
    }
    if (props.student.urls === undefined) {
        return;
    }
    try {
        isSaving.value = true;
        window.addEventListener("beforeunload", beforeUnloadHandler);
        const formData = new FormData();
        formData.append("comment", comment.value);
        const response = await fetch(props.student.urls.attemptComment, {
            method: "POST",
            body: formData,
        });
        if (response.ok) {
            emit("updatedComment", comment.value);
            hasSaved.value = true;
        } else {
            hasError.value = true;
        }
        window.removeEventListener("beforeunload", beforeUnloadHandler);
        isSaving.value = false;
    } catch (error) {
        hasError.value = true;
    }
    await timeout(2000);
    hasSaved.value = false;
    hasError.value = false;
}

/**
 * save the comment when it's changed
 */
watchDebounced(
    comment,
    () => {
        void saveComment();
    },
    { debounce: 1000 },
);

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

/**
 * Save any unsaved changes
 */
window.Vue.onUnmounted(async () => {
    await saveComment();
});
</script>

<template>
    <div class="c-island l-pad-m">
        <div class="l-flex-row l-gap-m">
            <div ref="gradeEl"></div>
            <div class="l-flex__item--1-grow">
                <p
                    v-if="
                        (props.isReadOnly && props.student.attemptResponse.comment === null) ||
                        (props.isReadOnly && props.student.attemptResponse.comment === '')
                    "
                >
                    <em>No comment added.</em>
                </p>
                <p v-else-if="props.isReadOnly && props.student.attemptResponse.comment !== ''">
                    {{ props.student.attemptResponse.comment }}
                </p>
                <template v-else>
                    <label :for="editorId">Comment</label>
                    <textarea :id="editorId" v-model="comment" rows="3" class="f-no-margins"></textarea>
                    <div class="save-container l-grid">
                        <div class="save-container__inner-container">
                            <div
                                :class="`alert-box f-no-margins ${hasSaved ? 'success' : ''} ${hasError ? 'alert' : ''}`"
                            >
                                <em>{{ saveText }}</em>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.save-container {
    grid-template-rows: v-bind(saveContainerHeight);
    transition: grid-template-rows 300ms;
    &__inner-container {
        overflow: hidden;
    }
}
</style>
