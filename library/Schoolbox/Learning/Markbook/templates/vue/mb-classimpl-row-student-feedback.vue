<script setup lang="ts">
/**
 * Please note, this component is NOT compatible with its props changing, use a :key to force a re-render
 * This is primarily caused by some dependencies down the chain of some of our jquery plugins, which makes it difficult to keep things in the right state
 */

import { cloneDeep } from "lodash";
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
    <template v-if="mbStore.data !== null && cellInfo !== null">
        <form
            ref="feedbackRef"
            class="l-pad-s--top"
            :class="{ 'mb-feedback-loading': mbStore.feedbackUILoadingInfo !== null }"
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
    </template>
</template>

<style scoped lang="scss">
.mb-feedback-loading {
    opacity: 0.5;
    pointer-events: none;

    .mb-feedback-close {
        pointer-events: all; // in case the user wants to abandon it still (or it gets stuck from an error or something...)
    }
}
</style>
