<script setup lang="ts">
/**
 * Please note, this component is NOT compatible with its props changing, use a :key to force a re-render
 * This is primarily caused by some dependencies down the chain of some of our jquery plugins, which makes it difficult to keep things in the right state
 */

import { cloneDeep } from "lodash";
import type { Ref } from "vue";
import { useTemplateRefsList, useElementBounding } from "@vueuse/core";
import locale from "@js/terminology/rosettaStone";
import CommentField from "@schoolboxLibrary/Learning/Assessment/templates/dueWork/vue/comment-field.vue";
import ResubmissionRequired from "@schoolboxLibrary/Learning/Assessment/templates/dueWork/vue/resubmission-required.vue";
import MarkScheme from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/mark-scheme.vue";
import { ACTIVITY_TYPE_DUE_WORK, ACTIVITY_TYPE_QUIZ } from "@schoolboxAssessment/Types/assessmentTypes";
import { useMarkbookStore } from "./store/markbookStore";
import type { MarkbookClassImplStudent } from "./types/payload";
import { useDynamicFeedback } from "./store/useDynamicFeedback";

interface Props {
    classImplId: number;
    activityId: number;
    studentId: MarkbookClassImplStudent["id"];
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const commentRef = window.Vue.ref<string | null>("");
const markRef = window.Vue.ref<string | null>(null);
const resubmissionRequiredRef = window.Vue.ref<boolean>(false);

/**
 * Retrieve the cell information for the current props
 * This is done outside of a computed property to avoid unnecessary re-computations
 * @returns studentId, activity, classImpl, aImpl, participant, feedback
 */
// eslint-disable-next-line @typescript-eslint/explicit-function-return-type -- we want this more complex type to be inferred
function getCellInfo() {
    if (mbStore.data === null) {
        return null;
    }

    const activity = mbStore.data.assessments[props.activityId];
    if (activity === undefined) {
        console.warn("Activity not found");
        return null;
    }

    const classImpl = mbStore.data.classImpls[props.classImplId];
    if (activity === undefined) {
        console.warn("Class implementation not found");
        return null;
    }

    const aImpl = classImpl.assessments[props.activityId];
    if (aImpl === undefined) {
        console.warn("aImpl not found");
        return null;
    }

    const student = classImpl.students[props.studentId];
    if (student === undefined) {
        console.warn("Student not found");
        return null;
    }

    const participant = aImpl.participants[props.studentId];
    if (participant === undefined) {
        console.warn("Participant not found");
        return null;
    }

    const { postFeedback } = aImpl._routes.student[props.studentId];
    if (postFeedback === null) {
        console.warn("Post feedback route not found");
        return null;
    }

    let extraFormData = {};
    const instructorMarkScheme = cloneDeep(aImpl.instructorMarkScheme);

    if (activity.assessmentType === ACTIVITY_TYPE_QUIZ) {
        /** For quizzes, we need to include the  maxMark in the extra form data */
        const maxMarks = participant.extraInfo?.maxMarks ?? null;

        extraFormData = { maxMarks };

        /** Also- the mark scheme's mask is derived from the extra data too for learnosity reasons */
        if (instructorMarkScheme.template === "TotalMarkFractional") {
            if (maxMarks === null) {
                throw new Error("Max marks not found in extraInfo for quiz");
            }

            instructorMarkScheme.context.conf.totalMark = maxMarks;
        }
    }

    const feedback = useDynamicFeedback(
        { hrMarkInstructor: markRef, comment: commentRef, resubmissionRequired: resubmissionRequiredRef },
        {
            hrMarkInstructor: window.Vue.toRef(participant.marks.draft, "hrInstructor"),
            hrMark: window.Vue.toRef(participant.marks.draft, "hr"),
            normMark: window.Vue.toRef(participant.marks.draft, "norm"),
            comment: window.Vue.toRef(participant.marks.draft, "comment"),
            resubmissionRequired: window.Vue.toRef(participant.marks.draft, "resubmissionRequired"),
            responseId: window.Vue.toRef(participant.marks.draft, "responseId"),
        },
        {
            hrMarkInstructor: window.Vue.toRef(participant.marks.published, "hrInstructor"),
            hrMark: window.Vue.toRef(participant.marks.published, "hr"),
            normMark: window.Vue.toRef(participant.marks.published, "norm"),
            comment: window.Vue.toRef(participant.marks.published, "comment"),
            resubmissionRequired: window.Vue.toRef(participant.marks.published, "resubmissionRequired"),
            responseId: window.Vue.toRef(participant.marks.published, "responseId"),
        },
        {
            hasSubmittedResponse: window.Vue.toRef(participant, "hasSubmittedResponse"),
            hasPublishedFeedback: window.Vue.toRef(participant, "hasPublishedFeedback"),
            plan: window.Vue.reactive(participant.plan),
        },
        {
            route: postFeedback,
            extraFormData,
        },
        activity.assessmentType === ACTIVITY_TYPE_QUIZ,
    );

    /** For due work, add some extra info like the status page does */
    const feedbackIncludes: string[] = [];
    const feedbackIncludesLink = aImpl._routes.student[student.id]["learningDueWorkResponse.getParticularResponse"];
    if (activity.assessmentType === ACTIVITY_TYPE_DUE_WORK && participant.extraInfo) {
        if (participant.extraInfo.feedback.responseWithKamiDocs) {
            feedbackIncludes.push("annotated document");
        }

        if (participant.extraInfo.feedback.rubric) {
            feedbackIncludes.push("rubric");
        }

        if (participant.extraInfo.feedback.fileCount) {
            feedbackIncludes.push(`file${participant.extraInfo.feedback.fileCount > 1 ? "s" : ""}`);
        }
    }

    return {
        student,
        activity,
        classImpl,
        aImpl,
        participant,
        instructorMarkScheme,
        feedback,
        feedbackIncludes,
        feedbackIncludesLink,
    };
}

const cellInfo = window.Vue.ref<ReturnType<typeof getCellInfo> | null>(getCellInfo());

/**
 * I can't figure out a way to force one particular table cell to be arbitrarily sticky
 * in the middle of the table, so force it to exist in the right spot instead
 *
 * The alternative will likely involve rewriting the markbook table as a css grid, like the unit planner...
 */

const resizeObserver = window.Vue.ref<ResizeObserver | null>(null);
const tableRowEl = window.Vue.useTemplateRef<HTMLTableRowElement>("tableRowRef");
const feedbackEl = window.Vue.useTemplateRef<HTMLElement>("feedbackRef");
const placeholderEl = window.Vue.useTemplateRef<HTMLDivElement>("placeholderRef");
const leftEls = useTemplateRefsList<HTMLTableCellElement>();

// tableContainerEl is the element that contains the markbook table,
// it gives us the actual bounds we are restricted to, rather than the table's bounds
const tableContainerEl = window.Vue.inject<Ref<HTMLElement>>("markbookTableContainerEl");
if (tableContainerEl === undefined) {
    throw new Error("markbookTableContainerEl is not provided");
}

const targetLeftEl = window.Vue.computed(() => leftEls.value[leftEls.value.length - 1]);

const tableContainerElBounds = useElementBounding(tableContainerEl);
const tableRowElBounds = useElementBounding(tableRowEl);
const feedbackElBounds = useElementBounding(feedbackEl);
const leftElBounds = useElementBounding(targetLeftEl);

const feedbackCellOffsets = window.Vue.computed(() => {
    const tableScrollbarOffsetX = tableContainerEl.value
        ? tableContainerElBounds.width.value - tableContainerEl.value.clientWidth
        : 0;
    const tableScrollbarOffsetY = tableContainerEl.value
        ? tableContainerElBounds.height.value - tableContainerEl.value.clientHeight
        : 0;

    // The maximum width that the feedback cell can take up, ensuring that it's fully visible on the screen
    const maxWidth =
        // The available width, excluding the extra room introduced by the rotated headers
        tableRowElBounds.width.value +
        tableRowElBounds.left.value -
        // To account for the left column, which can end up off the screen if you are on a small screen
        Math.max(leftElBounds.right.value, tableScrollbarOffsetX) -
        // The offset to account for the rotated headers and scrollbar
        Math.max(tableRowElBounds.right.value - tableContainerElBounds.right.value + tableScrollbarOffsetX, 0);

    const elementLeft = Math.max(leftElBounds.right.value, tableContainerElBounds.left.value);
    const elementTop = leftElBounds.top.value;

    // Since the feedback cell is fixed, it will show up outside the table bounds, so clip it instead
    const clipTop = Math.max(0, tableContainerElBounds.top.value - elementTop);
    const clipBottom = Math.max(
        0,
        elementTop + feedbackElBounds.height.value - tableContainerElBounds.bottom.value + tableScrollbarOffsetY + 10, // the 10 is somewhat arbitrary... i can't find a calculation that works without it yet
    );

    const clipPath = `inset(${clipTop}px 0px ${clipBottom}px 0px)`;

    return {
        left: elementLeft,
        top: elementTop,
        height: feedbackElBounds.height.value,
        maxWidth,
        clipPath,
    };
});

/**
 * Trigger a re-evaluation of the feedback cell position
 */
function updateFeedbackCellPosition(): void {
    tableContainerElBounds.update();
    feedbackElBounds.update();
    leftElBounds.update();
}

window.Vue.onMounted(async () => {
    // Initialize the ResizeObserver
    resizeObserver.value = new ResizeObserver(updateFeedbackCellPosition);
    resizeObserver.value.observe(document.querySelector("#container")!);
    resizeObserver.value.observe(document.querySelector("#markbook-outer-container")!);

    // Set the initial position, and update it again when the DOM is fully loaded to ensure the footer is included
    updateFeedbackCellPosition();
    window.addEventListener("DOMContentLoaded", updateFeedbackCellPosition, { once: true });

    if (placeholderEl.value) {
        await window.Vue.nextTick();
        // Ensure the table row is always in the center of the visible table area
        placeholderEl.value.scrollIntoView({ block: "center", behavior: "smooth" });
    }
});

window.Vue.onUnmounted(() => {
    if (resizeObserver.value) {
        resizeObserver.value.disconnect();
    }
});

/**
 * When the feedback is published, if it's successful - close the cell
 */
async function onFeedbackPublish(): Promise<void> {
    if (cellInfo.value === null) {
        return;
    }

    const result = await cellInfo.value.feedback.publishFeedback();
    if (result) {
        mbStore.setFeedbackSelectedCell(null);
    }
}
</script>

<template>
    <tr v-if="mbStore.data !== null" ref="placeholderRef">
        <th class="fixed-left is-selected"></th>
        <th class="fixed-left is-selected"></th>
        <th v-if="mbStore.data.rank !== null" class="is-selected"></th>
        <td :colspan="mbStore.orderedActivities.length" class="is-selected"></td>
    </tr>
    <tr v-if="mbStore.data !== null && cellInfo !== null" ref="tableRowRef">
        <th :ref="leftEls.set" class="fixed-left white"></th>
        <th :ref="leftEls.set" class="fixed-left white"></th>
        <th v-if="mbStore.data.rank !== null" class="white"></th>

        <td :colspan="mbStore.orderedActivities.length">
            <form
                ref="feedbackRef"
                class="fixed-feedback-cell row"
                :class="{
                    'fixed-feedback-cell--loading': mbStore.isFeedbackUILoading,
                }"
                :style="{
                    left: `${feedbackCellOffsets.left}px`,
                    top: `${feedbackCellOffsets.top}px`,
                    maxWidth: `${feedbackCellOffsets.maxWidth}px`,
                    clipPath: feedbackCellOffsets.clipPath,
                }"
            >
                <div v-if="cellInfo.instructorMarkScheme.template.length" class="small-12 medium-3 column">
                    <label>
                        {{ locale.grades.proper() }}
                        <mark-scheme
                            v-model:mark="cellInfo.feedback.data.hrMarkInstructor"
                            :participant="{ user: cellInfo.student }"
                            :mark-scheme="cellInfo.instructorMarkScheme"
                            :submission-error-states="mbStore.data.submissionErrorStates"
                            :limit="
                                cellInfo.participant.extraInfo?.maxMarks ?? undefined /** special quiz max mark chaos */
                            "
                        />
                    </label>
                    <resubmission-required
                        v-if="cellInfo.activity.assessmentType === ACTIVITY_TYPE_DUE_WORK"
                        v-model:resubmission-required="cellInfo.feedback.data.resubmissionRequired"
                        :identifier="cellInfo.student.id"
                        style="margin-top: 0.5rem"
                    />
                </div>
                <div class="small-12 column" :class="{ 'medium-9': cellInfo.instructorMarkScheme.template.length }">
                    <comment-field
                        v-model:comment="cellInfo.feedback.data.comment"
                        :participant="{ user: cellInfo.student }"
                        :is-rich-text="true"
                        :templates="mbStore.editorTemplates"
                    />

                    <resubmission-required
                        v-if="
                            cellInfo.activity.assessmentType === ACTIVITY_TYPE_DUE_WORK &&
                            cellInfo.instructorMarkScheme.template.length === 0
                        "
                        v-model:resubmission-required="resubmissionRequiredRef"
                        :identifier="cellInfo.student.id"
                        style="margin-top: 0.5rem"
                    />
                </div>
                <div class="small-12 column no-pad">
                    <div class="component-action" data-action-bar="60" data-mass-publish>
                        <section>
                            <div v-if="cellInfo.feedbackIncludes.length" class="component-action-info">
                                Feedback also includes
                                <a v-if="cellInfo.feedbackIncludesLink" :href="cellInfo.feedbackIncludesLink">
                                    {{ cellInfo.feedbackIncludes.join(", ") }}
                                </a>
                                <span v-else>{{ cellInfo.feedbackIncludes.join(", ") }}</span>
                            </div>

                            <div>
                                <button
                                    class="button mb-feedback-close"
                                    type="button"
                                    @click="mbStore.setFeedbackSelectedCell(null)"
                                >
                                    Close
                                </button>
                                <button
                                    v-if="
                                        cellInfo.feedback.dataDraft.responseId !== null ||
                                        cellInfo.feedback.showDraftDialogue
                                    "
                                    class="button disabled"
                                    :class="{ 'saving-error': cellInfo.feedback.hasAutoSaveError }"
                                    title="You can discard this draft from the Grading Overview screen"
                                    data-test="assessment-response-info-saved-as-draft"
                                    disabled
                                >
                                    <template v-if="cellInfo.feedback.isAutoSaveInProgress">Saving...</template>
                                    <template v-else-if="cellInfo.feedback.hasAutoSaveError">Saving failed</template>
                                    <template v-else>Saved as Draft</template>
                                </button>
                                <button
                                    v-if="
                                        cellInfo.feedback.dataDraft.responseId !== null ||
                                        cellInfo.feedback.showDraftDialogue ||
                                        cellInfo.feedback.isPublishedDirty
                                    "
                                    class="button"
                                    type="submit"
                                    @click.prevent="onFeedbackPublish"
                                >
                                    <template v-if="cellInfo.feedback.isPublishInProgress">Publishing...</template>
                                    <template v-else-if="cellInfo.activity.assessmentType === ACTIVITY_TYPE_QUIZ">
                                        Save and Publish
                                    </template>
                                    <template v-else>Publish</template>
                                </button>
                            </div>
                        </section>
                    </div>
                </div>
            </form>
            <div :style="{ height: `${feedbackCellOffsets.height}px` }"></div>
        </td>
    </tr>
</template>

<style scoped lang="scss">
.fixed-feedback-cell {
    margin: 0;
    position: fixed;
    text-wrap: wrap;
    width: 100%;
    z-index: 1;

    &--loading {
        opacity: 0.5;
        pointer-events: none;

        .mb-feedback-close {
            pointer-events: all; // in case the user wants to abandon it still (or it gets stuck from an error or something...)
        }
    }
}

/* need to move the padding into the inner div so that the component actions are flush */
td:has(.fixed-feedback-cell) {
    padding: 0;
}

.fixed-feedback-cell > div {
    padding-top: 0.625rem;
    padding-bottom: 0.625rem;
}
</style>
