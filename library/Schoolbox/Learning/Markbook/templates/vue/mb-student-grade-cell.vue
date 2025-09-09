<script setup lang="ts">
import sanitizeHtml from "sanitize-html";
import { until } from "@vueuse/core";
import { keysOf } from "@js/util/arrayUtils";
import { getGradient } from "@js/util/cssUtils";
import ProgressTick from "@schoolboxLibrary/Learning/Assessment/templates/fragments/vue/progress-tick.vue";
import {
    ACTIVITY_TYPE_DUE_WORK,
    ACTIVITY_TYPE_LTI_ASSESSMENT,
    ACTIVITY_TYPE_QUIZ,
} from "@schoolboxAssessment/Types/assessmentTypes";
import { useMarkbookStore } from "./store/markbookStore";

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
 * To determine whether the pop-out menu should be available for this cell
 */
const hasPopOutMenu = window.Vue.computed(() => {
    if (mbStore.data === null || assessment.value === null) {
        return false;
    }

    // You must be a participant (or excluded)
    if (participant.value === null) {
        return false;
    }

    // You must have access to the pop-out menu
    if (!mbStore.data.canAccessPopOut) {
        return false;
    }

    // ...and the assessment must be of a type that supports it
    return [ACTIVITY_TYPE_DUE_WORK, ACTIVITY_TYPE_QUIZ, ACTIVITY_TYPE_LTI_ASSESSMENT].includes(
        assessment.value.assessmentType,
    );
});

/**
 * The indicator and notch title for due work/quiz
 */
const decorator = window.Vue.computed(() => {
    if (participant.value === null) {
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

    if (mbStore.showDrafts && participant.value.marks.draft.responseId !== null) {
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
    if (mark.norm === null) {
        return "–";
    }

    const relevantMark = mbStore.showDisplayMarks ? mark.hr : mark.hrInstructor;
    return relevantMark ?? "–";
}

const cellEl = window.Vue.useTemplateRef<HTMLTableCellElement>("cellRef");

const DOUBLE_CLICK_INTERVAL = 500; // ms
const lastClick = window.Vue.ref<number>(0);

/**
 * When the user clicks the cell, open the pop-out menu if available, otherwise do nothing
 */
async function onClickCell(): Promise<void> {
    const canAccessUpdateFeedback = participant.value && !participant.value.isExcluded;
    if (Date.now() - lastClick.value < DOUBLE_CLICK_INTERVAL && canAccessUpdateFeedback) {
        /**
         * The popout is responsible for loading some extra feedback data,
         * as we are skipping the popout buttons, we need to make sure that extra data
         * has finished loading for the current feedback UI to be shown correctly
         */
        mbStore.isFeedbackUILoading = true;
        await until(() => mbStore.isPopoutLoading).toBe(false);
        mbStore.isFeedbackUILoading = false;

        if (mbStore.popoutLoadError) {
            return; // something went wrong, abort
        }

        mbStore.popoutSelectedCell = null;
        mbStore.setFeedbackSelectedCell({
            classImplId: props.classImplId,
            studentId: props.studentId,
            activityId: props.assessmentId,
        });
        return;
    }

    if (hasPopOutMenu.value && cellEl.value) {
        mbStore.popoutSelectedCell = {
            classImplId: props.classImplId,
            studentId: props.studentId,
            activityId: props.assessmentId,
            referenceElement: cellEl.value,
        };
    }

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
</script>

<template>
    <td
        v-if="mbStore.data && classImpl && assessment && student"
        ref="cellRef"
        class="student-grade-cell"
        :class="{
            excluded: !participant || participant.isExcluded,
            'is-selected': isSelectedPopout || isSelectedFeedback,
        }"
        :data-test="`markbook-cell-${assessment.id}-${student.id}`"
        :data-pop-out-menu="hasPopOutMenu || undefined"
        @click="onClickCell"
    >
        <template v-if="participant === null || participant.isExcluded"></template>
        <div v-else-if="participant.progressTick" style="display: flex; justify-content: center">
            <progress-tick
                :progress-tick="participant.progressTick"
                :is-self-assessable="false"
                :can-modify="aImpl !== null && aImpl.writable !== false"
                no-margin
                @update:class="(newClass) => (participant!.progressTick!.progressClass = newClass)"
            />
        </div>
        <template v-else>
            <span
                v-if="mbStore.showDrafts && participant.marks.draft.responseId !== null"
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
                v-if="!participant.plan.isDynamic"
                class="individualisation has-tip tip-left"
                data-width="200"
                data-tooltip
                :title="`${participant.plan.name} (${participant.plan.dueDate})`"
            >
                i
            </span>
        </template>
    </td>
    <td v-else class="na"></td>
</template>

<style scoped></style>
