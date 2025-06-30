<script setup lang="ts">
import type { Ref } from "vue";
import { useTemplateRefsList, useElementBounding } from "@vueuse/core";
import locale from "@js/terminology/rosettaStone";
import CommentField from "@schoolboxLibrary/Learning/Assessment/templates/dueWork/vue/comment-field.vue";
import ResubmissionRequired from "@schoolboxLibrary/Learning/Assessment/templates/dueWork/vue/resubmission-required.vue";
import MarkScheme from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/mark-scheme.vue";
import { useMarkbookStore } from "./store/markbookStore";
import type { MarkbookClassImplStudent } from "./types/payload";

interface Props {
    classImplId: number;
    activityId: number;
    student: MarkbookClassImplStudent;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const cellInfo = window.Vue.computed(() => {
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

    const participant = aImpl.participants[props.student.id];
    if (participant === undefined) {
        console.warn("Participant not found");
        return null;
    }

    return {
        activity,
        classImpl,
        aImpl,
        participant,
    };
});

/**
 * I can't figure out a way to force one particular table cell to be arbitrarily sticky
 * in the middle of the table, so force it to exist in the right spot instead
 *
 * The alternative will likely involve rewriting the markbook table as a css grid, like the unit planner...
 */

const resizeObserver = window.Vue.ref<ResizeObserver | null>(null);
const tableRowEl = window.Vue.useTemplateRef<HTMLTableRowElement>("tableRowEl");
const feedbackEl = window.Vue.useTemplateRef<HTMLElement>("feedbackEl");
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

window.Vue.onMounted(() => {
    // Initialize the ResizeObserver
    resizeObserver.value = new ResizeObserver(updateFeedbackCellPosition);
    resizeObserver.value.observe(document.querySelector("#container")!);
    resizeObserver.value.observe(document.querySelector("#markbook-outer-container")!);

    // Set the initial position, and update it again when the DOM is fully loaded to ensure the footer is included
    updateFeedbackCellPosition();
    window.addEventListener("DOMContentLoaded", updateFeedbackCellPosition, { once: true });
});

window.Vue.onUnmounted(() => {
    if (resizeObserver.value) {
        resizeObserver.value.disconnect();
    }
});

// todo: make this actually work with draft/published feedback chaos
const comment = window.Vue.ref<string>("");
const mark = window.Vue.ref<string | null>(null);

window.Vue.watch(
    () => [props.classImplId, props.activityId, props.student.id],
    async () => {
        await window.Vue.nextTick();
        if (cellInfo.value === null || cellInfo.value.participant === undefined) {
            comment.value = "";
            mark.value = null;
            return;
        }

        const { marks } = cellInfo.value.participant;
        if (marks === undefined || marks === null) {
            comment.value = "";
        } else if (marks.draft && marks.draft.comment) {
            comment.value = marks.draft.comment;
        } else if (marks.published && marks.published.comment) {
            comment.value = marks.published.comment;
        } else {
            comment.value = "";
        }

        if (marks === undefined || marks === null) {
            mark.value = null;
        } else if (marks.draft && marks.draft.hrInstructor) {
            mark.value = marks.draft.hrInstructor;
        } else if (marks.published && marks.published.hrInstructor) {
            mark.value = marks.published.hrInstructor;
        } else {
            mark.value = null;
        }
    },
    { immediate: true },
);
</script>

<template>
    <tr v-if="mbStore.data !== null">
        <th :ref="leftEls.set" class="fixed-left is-selected"></th>
        <th :ref="leftEls.set" class="fixed-left is-selected"></th>
        <th v-if="mbStore.data.rank !== null" :ref="leftEls.set" class="fixed-left is-selected"></th>
        <td :colspan="mbStore.orderedActivities.length" class="fixed-left is-selected"></td>
    </tr>
    <tr v-if="mbStore.data !== null && cellInfo !== null" ref="tableRowEl">
        <th :ref="leftEls.set" class="fixed-left white"></th>
        <th :ref="leftEls.set" class="fixed-left white"></th>
        <th v-if="mbStore.data.rank !== null" :ref="leftEls.set" class="fixed-left white"></th>

        <td :colspan="mbStore.orderedActivities.length">
            <form
                ref="feedbackEl"
                class="fixed-feedback-cell row"
                :style="{
                    left: `${feedbackCellOffsets.left}px`,
                    top: `${feedbackCellOffsets.top}px`,
                    maxWidth: `${feedbackCellOffsets.maxWidth}px`,
                    clipPath: feedbackCellOffsets.clipPath,
                }"
            >
                <div v-if="cellInfo.aImpl.instructorMarkScheme.template.length" class="small-12 medium-3 column">
                    <label>
                        {{ locale.grades.proper() }}
                        <mark-scheme
                            :key="cellInfo.aImpl.instructorMarkScheme.template"
                            v-model:mark="mark"
                            :participant="{ user: props.student }"
                            :mark-scheme="cellInfo.aImpl.instructorMarkScheme"
                            :submission-error-states="mbStore.data.submissionErrorStates"
                        />
                    </label>
                    <resubmission-required
                        v-model:resubmission-required="cellInfo.participant.resubmissionRequired"
                        :identifier="props.student.id"
                        style="margin-top: 0.5rem"
                    />
                </div>
                <div
                    class="small-12 column"
                    :class="{ 'medium-9': cellInfo.aImpl.instructorMarkScheme.template.length }"
                >
                    <comment-field
                        v-model:comment="comment"
                        :participant="{ user: props.student }"
                        :is-rich-text="true"
                    />

                    <resubmission-required
                        v-if="cellInfo.aImpl.instructorMarkScheme.template.length === 0"
                        v-model:resubmission-required="cellInfo.participant.resubmissionRequired"
                        :identifier="props.student.id"
                        style="margin-top: 0.5rem"
                    />
                </div>
                <div class="small-12 column no-pad">
                    <div class="component-action" data-action-bar="60" data-mass-publish>
                        <section>
                            <div>
                                <button
                                    data-sobo
                                    title="Acknowledge receipt of student submission"
                                    href="/learning/assessments/767/60/received"
                                >
                                    Mark as Submitted
                                </button>
                                <button
                                    class="button disabled"
                                    data-draft="drafted"
                                    title="You can discard this draft from the Grading Overview screen"
                                    data-test="assessment-response-info-saved-as-draft"
                                    disabled
                                >
                                    Saved as Draft
                                </button>
                                <a class="button" data-draft="publish" data-draft-id="187">Publish</a>
                            </div>
                        </section>
                    </div>
                </div>
            </form>
            <div :style="{ height: `${feedbackCellOffsets.height}px` }"></div>
        </td>
    </tr>
</template>

<style scoped>
.fixed-feedback-cell {
    margin: 0;
    position: fixed;
    text-wrap: wrap;
    width: 100%;
    z-index: 1;
}

/* need to move the padding into the inner div so that the component actions are flush */
td:has(.fixed-feedback-cell) {
    padding: 0;
}

.fixed-feedback-cell > :nth-child(1) {
    padding-top: 0.625rem;
    padding-bottom: 0.625rem;
}
</style>
