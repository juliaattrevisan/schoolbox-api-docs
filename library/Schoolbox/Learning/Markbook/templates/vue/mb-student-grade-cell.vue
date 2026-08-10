<script setup lang="ts">
import sanitizeHtml from "sanitize-html";
import { until } from "@vueuse/core";
import { keysOf } from "@js/util/arrayUtils";
import { getGradient } from "@js/util/cssUtils";
import ProgressTick from "@schoolboxLibrary/Learning/Assessment/templates/fragments/vue/progress-tick.vue";
import {
    ACTIVITY_TYPE_DIALOG_AGENT,
    ACTIVITY_TYPE_DUE_WORK,
    ACTIVITY_TYPE_LTI_ASSESSMENT,
    ACTIVITY_TYPE_QUIZ,
    ACTIVITY_TYPE_REPORTING,
} from "@schoolboxAssessment/Types/assessmentTypes";
import { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import { useMarkbookStore } from "./store/markbookStore";
import { MarkbookGridCellType } from "./types/types";

interface Props {
    classImplId: number;
    studentId: number;
    assessmentId: number;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const classImpl = window.Vue.computed(() => {
    if (mbStore.data === null) return null;

    return mbStore.data.classImpls[props.classImplId] || null;
});

const assessment = window.Vue.computed(() => {
    if (mbStore.data === null) return null;

    return mbStore.data.assessments[props.assessmentId] || null;
});

const aImpl = window.Vue.computed(() => {
    if (classImpl.value === null) return null;

    return classImpl.value.assessments[props.assessmentId] || null;
});

const student = window.Vue.computed(() => {
    if (classImpl.value === null) return null;

    return classImpl.value.students[props.studentId] || null;
});

const participant = window.Vue.computed(() => {
    if (aImpl.value === null || student.value === null) return null;

    return aImpl.value.participants[student.value.id] || null;
});

/**
 * Whether the draft values should be shown if they exist.
 * For normal activities, this is controlled by the "Show Drafts" toggle in the markbook.
 * On the reporting status page, the cells for the activity itself aren't affected by the toggle and always prefer draft
 * values if they exist, to match the behaviour of the other status pages.
 */
const preferDraftFeedback = window.Vue.computed(() => {
    if (assessment.value === null) return false;

    if (assessment.value.splitComments) {
        /* "splitComments" is the way to define "the activity itself" for reporting activities at the moment,
         * I expect this will change in the near future though to something more explicit.
         */
        return true;
    }

    return mbStore.showDrafts;
});

const canGiveFeedbackMarks = window.Vue.computed(
    () => aImpl.value && aImpl.value.instructorMarkScheme.mode !== MarkTypeSlug.NONE,
);
const canGiveFeedbackComments = window.Vue.computed(() => aImpl.value && aImpl.value.feedbackCommentsEnable);

/**
 * To determine whether the pop-out menu should be available for this cell
 */
const hasPopOutMenu = window.Vue.computed(() => {
    if (mbStore.data === null || assessment.value === null) {
        return false;
    }

    // There must be a participant (or excluded)
    if (participant.value === null) {
        return false;
    }

    // You must have write access to the activity to be able to open the pop-out
    if (aImpl.value === null || !aImpl.value.writable) {
        return false;
    }

    // ...and the assessment must be of a type that supports it
    return [
        ACTIVITY_TYPE_DUE_WORK,
        ACTIVITY_TYPE_QUIZ,
        ACTIVITY_TYPE_DIALOG_AGENT,
        ACTIVITY_TYPE_LTI_ASSESSMENT,
        ACTIVITY_TYPE_REPORTING,
    ].includes(assessment.value.assessmentType);
});

/**
 * The indicator and notch title for due work/quiz.
 * This is not shown for splitComments/rubric cells as they have their own indicator on the reporting markbook.
 * TODO: ^ add the indicator in this scenario (in SA-23168), also splitComments could do with a better name.
 */
const decorator = window.Vue.computed(() => {
    if (participant.value === null || assessment.value?.splitComments) {
        return null;
    }

    if (
        !participant.value.hasSubmittedResponse &&
        !participant.value.marks.published.resubmissionRequired &&
        participant.value.marks.published.responseId === null &&
        participant.value.marks.draft.responseId === null
    ) {
        return null;
    }

    if (preferDraftFeedback.value && participant.value.marks.draft.responseId !== null) {
        return {
            indicator: "pending",
            notchTitle: "Unpublished Draft",
        };
    }

    /* resubmission required won't ever show for drafts, so it's only checked on published marks */
    if (participant.value.marks.published.resubmissionRequired) {
        return {
            indicator: "resubmit",
            notchTitle: "Resubmission Required",
        };
    }

    if (participant.value.hasPublishedFeedback) {
        return {
            indicator: "published",
            notchTitle: "Mark Published",
        };
    }

    return {
        indicator: "none",
        notchTitle: "Unmarked",
    };
});

/**
 * Format a mark to be displayed
 * If the norm mark is null, the mark may be an empty string; so fall back to a dash
 * If the mark doesn't exist at all, also return a dash
 * @param mark The mark to format, either a published or draft mark
 * @returns The formatted mark
 */
function formatMark(mark: NonNullable<typeof participant.value>["marks"]["published" | "draft"]): string {
    /* Any feedback that used a submission state will potentially have a null normalised mark; but, it will have a
     * meaningful human-readable mark. This unfortunately might share the same signature as a draft mark and/or a
     * published feedback with Resubmission Required.
     */
    if (mark.hrInstructor === "" && mark.norm === null) {
        return "–";
    }

    const relevantMark = mbStore.showDisplayMarks ? mark.hr : mark.hrInstructor;

    return relevantMark ?? "–";
}

const cellEl = window.Vue.useTemplateRef<HTMLTableCellElement>("cellRef");
const commentCellEl = window.Vue.useTemplateRef<HTMLTableCellElement>("commentCellRef");

const DOUBLE_CLICK_INTERVAL = 500; // ms
const lastClick = window.Vue.ref<number>(0);

/**
 * When the user clicks the cell, open the pop-out menu if available, otherwise do nothing
 * @param referenceElement The element to anchor the pop-out to
 * @param cellType Track the type of cell clicked (as rubric cells have different behaviour for dbl click/update feedback)
 */
async function onClickCell(
    referenceElement: HTMLTableCellElement | null,
    cellType: MarkbookGridCellType,
): Promise<void> {
    if (!hasPopOutMenu.value || referenceElement === null) {
        return; // must have access to the pop-out and a mounted cell element
    }

    // You can access the update feedback UI for this if the participant isn't excluded and you have postFeedback access
    const canAccessUpdateFeedback =
        student.value &&
        participant.value &&
        !participant.value.isExcluded &&
        aImpl.value?._routes.student[student.value.id].postFeedback;

    if (Date.now() - lastClick.value < DOUBLE_CLICK_INTERVAL && canAccessUpdateFeedback) {
        /**
         * The popout is responsible for loading some extra feedback data,
         * as we are skipping the popout buttons, we need to make sure that extra data
         * has finished loading for the current feedback UI to be shown correctly
         */
        const selectedFeedbackInfo = {
            classImplId: props.classImplId,
            studentId: props.studentId,
            activityId: props.assessmentId,
        };

        mbStore.feedbackUILoadingInfo = selectedFeedbackInfo;
        await until(() => mbStore.isPopoutLoading).toBe(false);

        if (JSON.stringify(mbStore.feedbackUILoadingInfo) !== JSON.stringify(selectedFeedbackInfo)) {
            return; // another cell took over, abort
        }

        mbStore.feedbackUILoadingInfo = null;

        if (mbStore.popoutLoadError) {
            return; // something went wrong, abort
        }

        mbStore.popoutSelectedCell = null;

        /* you must have the ability to either enter a mark or a comment (reporting can disable both at once) */
        if (canGiveFeedbackMarks.value || canGiveFeedbackComments.value) {
            mbStore.setFeedbackSelectedCell(selectedFeedbackInfo);
        }

        return;
    }

    mbStore.popoutSelectedCell = {
        classImplId: props.classImplId,
        studentId: props.studentId,
        activityId: props.assessmentId,
        referenceElement,
        cellType,
    };

    lastClick.value = Date.now();
}

const isSelectedPopout = window.Vue.computed(
    () =>
        mbStore.popoutSelectedCell &&
        mbStore.popoutSelectedCell.classImplId === props.classImplId &&
        mbStore.popoutSelectedCell.studentId === props.studentId &&
        mbStore.popoutSelectedCell.activityId === props.assessmentId,
);

const isSelectedFeedback = window.Vue.computed(
    () =>
        mbStore.feedbackSelectedCell &&
        mbStore.feedbackSelectedCell.classImplId === props.classImplId &&
        mbStore.feedbackSelectedCell.studentId === props.studentId &&
        mbStore.feedbackSelectedCell.activityId === props.assessmentId,
);

/**
 * The comment is given to us with HTML since it's needed in a CKEditor.
 * This strips all of the tags off and returns text instead
 */
const plaintextComments = window.Vue.computed(() => {
    if (participant.value === null) {
        return undefined;
    }

    const comments = {
        published: participant.value.marks.published.comment,
        draft: participant.value.marks.draft.comment,
    };

    keysOf(comments).forEach((key) => {
        if (comments[key] === null) {
            return;
        }

        /** replace <br>'s so that it doesn't loose an effective space */
        comments[key] = comments[key].replaceAll("<br />", " ").replaceAll("<br>", " ");

        /** then strip all the tags */
        comments[key] = sanitizeHtml(comments[key], {
            allowedTags: [],
            allowedAttributes: {},
        }).trim();
    });

    return comments;
});

// show the marks column if splitComments is disabled or if the user can give feedback marks
const showMarksColumn = window.Vue.computed(() => {
    if (assessment.value === null) return false;
    return !assessment.value.splitComments || canGiveFeedbackMarks.value;
});

// show the comments column if splitComments is enabled and the user can give feedback comments
const showCommentsColumn = window.Vue.computed(() => {
    if (assessment.value === null) return false;
    return assessment.value.splitComments && canGiveFeedbackComments.value;
});
</script>

<template>
    <td
        v-if="mbStore.data && classImpl && assessment && student && showMarksColumn"
        ref="cellRef"
        class="student-grade-cell"
        :class="{
            excluded: !participant || participant.isExcluded,
            'is-selected':
                isSelectedFeedback || (isSelectedPopout && mbStore.popoutSelectedCell?.referenceElement === cellEl),
        }"
        :data-test="`markbook-cell-${assessment.id}-${student.id}`"
        :data-pop-out-menu="hasPopOutMenu || undefined"
        @click="onClickCell(cellEl, MarkbookGridCellType.MARK)"
    >
        <template v-if="participant === null || participant.isExcluded"></template>
        <div v-else-if="participant.progressTick" style="display: flex; justify-content: center">
            <progress-tick
                :progress-tick="participant.progressTick"
                :is-self-assessable="false"
                :can-modify="aImpl !== null && aImpl.writable"
                no-margin
                @update:class="(newClass) => (participant!.progressTick!.progressClass = newClass)"
            />
        </div>
        <template v-else>
            <span
                v-if="preferDraftFeedback && participant.marks.draft.responseId !== null"
                :title="plaintextComments?.draft ?? undefined"
                class="meta"
            >
                {{ formatMark(participant.marks.draft) }}
            </span>
            <span
                v-else-if="participant.hasSubmittedResponse && !participant.hasPublishedFeedback"
                :title="plaintextComments?.published ?? undefined"
            >
                Pending
            </span>
            <span
                v-else-if="participant.marks.published.responseId !== null"
                :title="plaintextComments?.published ?? undefined"
                :class="getGradient(participant.marks.published.norm, 100, false)"
            >
                {{ formatMark(participant.marks.published) }}
            </span>
            <span v-else>–</span>

            <span
                v-if="decorator !== null"
                class="legend-indicator"
                :class="`indicator-${decorator.indicator}`"
                :title="decorator.notchTitle"
            ></span>

            <span
                v-if="participant.plan.isStatic"
                class="individualisation has-tip tip-left"
                data-width="200"
                data-tooltip
                :title="`${participant.plan.name} (${participant.plan.dueDate})`"
            >
                i
            </span>
        </template>
    </td>
    <td v-else-if="showMarksColumn" class="na"></td>

    <!-- Reporting Markbook: Split comments field for the reporting activity being viewed -->
    <td
        v-if="mbStore.data && classImpl && assessment && student && showCommentsColumn"
        ref="commentCellRef"
        class="mb-student-grade-cell-comment"
        :class="{
            excluded: !participant || participant.isExcluded,
            'is-selected':
                isSelectedFeedback ||
                (isSelectedPopout && mbStore.popoutSelectedCell?.referenceElement === commentCellEl),
        }"
        :data-test="`markbook-cell-${assessment.id}-${student.id}-comment`"
        :data-pop-out-menu="hasPopOutMenu || undefined"
        @click="onClickCell(commentCellEl, MarkbookGridCellType.COMMENT)"
    >
        <template v-if="participant === null || participant.isExcluded"></template>

        <!-- eslint-disable vue/no-v-html -- scrubbed in the backend -->
        <span
            v-else-if="preferDraftFeedback && participant.marks.draft.responseId !== null"
            class="meta"
            v-html="participant.marks.draft.comment"
        />
        <!-- eslint-enable vue/no-v-html -->
        <!-- eslint-disable vue/no-v-html -- scrubbed in the backend -->
        <span
            v-else-if="participant.marks.published.responseId !== null"
            v-html="participant.marks.published.comment"
        />
        <!-- eslint-enable vue/no-v-html -->

        <span v-else>–</span>
    </td>
    <td v-else-if="showCommentsColumn" class="na"></td>
</template>

<style scoped>
.mb-student-grade-cell-comment {
    min-width: 40rem;
    white-space: normal !important;
}
</style>
