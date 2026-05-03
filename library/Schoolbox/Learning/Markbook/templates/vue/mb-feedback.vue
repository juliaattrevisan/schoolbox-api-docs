<script setup lang="ts">
import { cloneDeep } from "es-toolkit";
import locale from "@js/terminology/rosettaStone";
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer.vue";
import CommentField from "@schoolboxLibrary/Learning/Assessment/templates/dueWork/vue/comment-field.vue";
import ResubmissionRequired from "@schoolboxLibrary/Learning/Assessment/templates/dueWork/vue/resubmission-required.vue";
import MarkScheme from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/mark-scheme.vue";
import { ACTIVITY_TYPE_DUE_WORK, ACTIVITY_TYPE_QUIZ } from "@schoolboxAssessment/Types/assessmentTypes";
import { useMarkbookStore } from "./store/markbookStore";
import { useDynamicFeedback } from "./store/useDynamicFeedback";
import type { MarkbookClassImpl, MarkbookClassImplActivity, MarkbookClassImplStudent } from "./types/payload";

interface Props {
    maxHeight: string;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const feedbackEl = window.Vue.useTemplateRef<HTMLElement | null>("feedbackRef");

const lazyCommentRef = window.Vue.ref<string | null>(""); // for lazy loading the editor

const commentRef = window.Vue.ref<string | null>("");
const markRef = window.Vue.ref<string | null>(null);
const resubmissionRequiredRef = window.Vue.ref<boolean>(false);

/**
 * When any feedback is saved as a draft or published, update any other relevant data
 * @param aImpl - The activity implementation the feedback was for
 * @param classImpl - The class implementation the activity implementation belongs to
 * @param student - The student the feedback was for
 */
function onFeedbackSaved(
    aImpl: MarkbookClassImplActivity,
    classImpl: MarkbookClassImpl,
    student: MarkbookClassImplStudent,
): void {
    /** we need to refresh the project stats if this is part of a project */
    const projectImpl = aImpl.projectId ? classImpl.assessments[aImpl.projectId] : undefined;
    if (projectImpl !== undefined) {
        const infoRoute = projectImpl._routes.student[student.id]["learningAssessment.getInfo"];
        const participant = projectImpl.participants[student.id];
        if (infoRoute !== null && participant !== undefined) {
            // void to let it run in the background and not block anything, it'll update when it wants to
            void mbStore.updateExtraInfo(infoRoute, participant);
        }
    }
}

/**
 * Retrieve the cell information for the current props
 * This is done outside of a computed property to avoid unnecessary re-computations
 * @returns studentId, activity, classImpl, aImpl, participant, feedback
 */
// eslint-disable-next-line @typescript-eslint/explicit-function-return-type -- we want this more complex type to be inferred
function getCellInfo() {
    if (mbStore.data === null || mbStore.feedbackSelectedCell === null) {
        return null;
    }

    const { classImplId, activityId, studentId } = mbStore.feedbackSelectedCell;

    const activity = mbStore.data.assessments[activityId];
    if (activity === undefined) {
        console.warn("Activity not found");
        return null;
    }

    const classImpl = mbStore.data.classImpls[classImplId];
    if (activity === undefined) {
        console.warn("Class implementation not found");
        return null;
    }

    const aImpl = classImpl.assessments[activityId];
    if (aImpl === undefined) {
        console.warn("aImpl not found");
        return null;
    }

    const student = classImpl.students[studentId];
    if (student === undefined) {
        console.warn("Student not found");
        return null;
    }

    const participant = aImpl.participants[studentId];
    if (participant === undefined) {
        console.warn("Participant not found");
        return null;
    }

    const { postFeedback } = aImpl._routes.student[studentId];
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

    // Make sure to clear the existing ref values in case there is no initial data for them to be set to
    commentRef.value = "";
    markRef.value = null;
    resubmissionRequiredRef.value = false;

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
        () => onFeedbackSaved(aImpl, classImpl, student),
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
        /** We need to key the whole form to ensure some of the jquery libraries down the chain attach properly each time it changes */
        key: Object.values(mbStore.feedbackSelectedCell).join("-"),
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

const cellInfo = window.Vue.ref<ReturnType<typeof getCellInfo> | null>(null);

window.Vue.watch(
    () => mbStore.feedbackSelectedCell,
    async () => {
        // Must clean up any existing feedback instance first, otherwise watchers build up and remain active...
        if (cellInfo.value !== null) {
            cellInfo.value.feedback.cleanup();
        }

        if (mbStore.feedbackSelectedCell !== null) {
            cellInfo.value = getCellInfo();

            const DRAWER_ANIMATION_TIME_MS = 300;

            await window.Vue.nextTick();
            setTimeout(() => {
                if (feedbackEl.value === null) return;
                mbStore.feedbackDrawerEventBus.emit({
                    drawerRect: feedbackEl.value.parentElement!.getBoundingClientRect(),
                });
            }, DRAWER_ANIMATION_TIME_MS);
        }

        // If it's now null, leave cellInfo set to whatever it was so the user sees the drawer content slide down correctly
    },
    { immediate: true },
);

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
    <bottom-drawer :show-drawer="mbStore.feedbackSelectedCell !== null" :has-padding="false">
        <form
            v-if="mbStore.data !== null && cellInfo !== null"
            :key="cellInfo.key"
            ref="feedbackRef"
            class="mb-feedback l-pad-s--top"
            :class="{ 'mb-feedback--loading': mbStore.feedbackUILoadingInfo !== null }"
        >
            <div v-if="cellInfo.instructorMarkScheme.template.length" class="small-12 medium-3 column">
                <label>
                    {{ locale.grades.proper() }}
                    <mark-scheme
                        :id="`mark-${cellInfo.student.id}`"
                        v-model:mark="cellInfo.feedback.data.hrMarkInstructor"
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
            <div
                class="small-12 column mb-feedback-comment"
                :class="{ 'medium-9': cellInfo.instructorMarkScheme.template.length }"
                :style="{ maxHeight: `min(${props.maxHeight}, 90vh)` }"
            >
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
        <!--
        this is literally here just to lazy load an editor behind the scenes and make it so that the
        first time you open up the drawer it feels MUCH faster. the ckeditor appears to take a decently
        noticable time to load so this avoids having to deal with that and then seeing a jerk in the UI
        -->
        <comment-field
            v-else
            v-model:comment="lazyCommentRef"
            :participant="{ user: { id: 0, givenName: '' } }"
            :is-rich-text="true"
            :templates="mbStore.editorTemplates"
        />
    </bottom-drawer>
</template>

<style scoped lang="scss">
.mb-feedback {
    &--loading {
        opacity: 0.5;
        pointer-events: none;

        .mb-feedback-close {
            pointer-events: all; // in case the user wants to abandon it still (or it gets stuck from an error or something...)
        }
    }

    // the comment could get HUGE so we need to limit it a bit, this amount seems reasonable
    // this creates a 3rd scrollbar on the page... but that's probably better than the drawer being too big
    .mb-feedback-comment {
        overflow-y: auto;
    }
}
</style>
