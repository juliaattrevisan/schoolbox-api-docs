<script setup lang="ts">
/**
 * Bulk copy actions in the reporting markbook popout.
 *
 * These are currently only available for mark/comment fields on the reporting markbook,
 * if we expanded this to be available for normal mark fields on the course markbook then you'd have
 * to consider implications of copying marks between classes which could lead to differing mark types...
 */

import locale from "@js/terminology/rosettaStone";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { assertUnreachable } from "@js/util/errorUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import PopOutMenuItem from "@schoolboxVue/components/menu/pop-out-menu-item.vue";
import PopOutMenuDivider from "@schoolboxVue/components/menu/pop-out-menu-divider.vue";
import { useMarkbookStore } from "./store/markbookStore";
import type {
    MarkbookClassImpl,
    MarkbookClassImplActivity,
    MarkbookClassImplActivityParticipant,
} from "./types/payload";
import { MarkbookGridCellType } from "./types/types";

interface Props {
    /** The popout cell to copy from; mutable row data is re-read from the store, not this prop */
    // selectedCell: MarkbookPopoutSelectedCellInfo;
    // classImpl: MarkbookClassImpl;
    /** The supported types of cells that feedback can be copied from (rubrics are readonly) */
    cellType: MarkbookGridCellType.MARK | MarkbookGridCellType.COMMENT;
    /** The relevant class implementation that the source participant belongs to */
    classImpl: MarkbookClassImpl;
    /** The activity implementation for the selected cell (containing the other participants that can be copied to) */
    aImpl: MarkbookClassImplActivity;
    /** The ID of the student associated with the cell (where the feedback will be copied from) */
    studentId: number;
    /** The participant associated with the cell (where the feedback will be copied from) */
    participant: MarkbookClassImplActivityParticipant;
}

const props = defineProps<Props>();
const emit = defineEmits(["copying"]);
const mbStore = useMarkbookStore();

const isConfirmModalOpen = window.Vue.ref(false);
const isCopying = window.Vue.ref(false);

/** A label for the type of feedback being copied */
const fieldLabel = window.Vue.computed(() => {
    if (props.cellType === MarkbookGridCellType.MARK) return locale.grades.singular();
    if (props.cellType === MarkbookGridCellType.COMMENT) return "comment";
    assertUnreachable(props.cellType);
});

type ParticipantFeedback = MarkbookClassImplActivityParticipant["marks"]["draft" | "published"];

/**
 * Determine which feedback mark data represents the visible value in the cell,
 * (the value that will be copied from, or the value compared to when overriding).
 *
 * Theoretically this should match whatever `mb-student-grade-cell.vue` is showing.
 * @param participant The participant to get the visible feedback for
 * @returns The visible feedback mark data, or null if there is no visible feedback
 */
function getVisibleFeedback(participant: MarkbookClassImplActivityParticipant): ParticipantFeedback | null {
    // drafts always take precedence over published, the "Show Drafts" toggle is meaningless for cells that can have these actions
    if (participant.marks.draft.responseId !== null) {
        return participant.marks.draft;
    }

    if (participant.marks.published.responseId !== null) {
        return participant.marks.published;
    }

    return null;
}

/**
 * Determine whether a particular draft or published feedback has a meaningful value.
 * @param feedback The feedback to check
 * @returns true if meaningful
 */
function feedbackHasMeaningfulValue(feedback: ParticipantFeedback | null): boolean {
    if (feedback === null) {
        return false; // no feedback at all, nothing to copy or compare to
    }

    if (props.cellType === MarkbookGridCellType.MARK) {
        // For the Mark column, should have either a meaningful instructor mark, student mark, or normalised mark.
        // Any feedback that used a submission state will potentially have a null normalised mark.
        return feedback.hrInstructor !== null || feedback.hr !== null || feedback.norm !== null;
    }

    if (props.cellType === MarkbookGridCellType.COMMENT) {
        // For the Comment column, should have a non-empty comment.
        return feedback.comment !== null && feedback.comment !== "";
    }

    assertUnreachable(props.cellType);
}

/** The visible feedback mark data that will be copied from (draft or published) */
const visibleFeedback = window.Vue.computed(() => getVisibleFeedback(props.participant));

/** Whether it's possible to copy from this cell (must have a meaningful value). */
const hasValueToCopy = window.Vue.computed(() => feedbackHasMeaningfulValue(visibleFeedback.value));

/**
 * Determine if a particular feedback differs to visibleFeedback in a way that would be overridden by copying.
 * @param feedback The feedback to compare to visibleFeedback
 * @returns true if the feedback differs
 */
function compareFeedback(feedback: ParticipantFeedback | null): boolean {
    if (visibleFeedback.value === null) {
        return false; // nothing to compare to, so can't override
    }

    if (props.cellType === MarkbookGridCellType.MARK) {
        return (
            feedback?.hrInstructor !== visibleFeedback.value.hrInstructor ||
            feedback?.hr !== visibleFeedback.value.hr ||
            feedback?.norm !== visibleFeedback.value.norm
        );
    }

    if (props.cellType === MarkbookGridCellType.COMMENT) {
        return feedback?.comment !== visibleFeedback.value.comment;
    }

    assertUnreachable(props.cellType);
}

type TargetParticipant = {
    participant: MarkbookClassImplActivityParticipant;
    postFeedback: string;
    relevantFeedback: ParticipantFeedback | null;
    hasValueToOverride: boolean;
};

/**
 * The participants which a bulk copy would write to (any students in the class impl, except for the source
 * participant and any participants that the user can't post feedback for).
 * Also whether or not they'd be overridden (already have a value that differs to the value that would be applied).
 */
const targetParticipants = window.Vue.computed<TargetParticipant[]>(() => {
    if (visibleFeedback.value === null) {
        return [];
    }

    return mbStore
        .orderedClassImplStudents(props.classImpl)
        .filter((student) => student.id !== props.studentId) // all other participants in the class impl
        .map((student): TargetParticipant | null => {
            const participant = props.aImpl.participants[student.id] ?? null;
            const postFeedback = props.aImpl._routes.student[student.id]?.postFeedback ?? null;
            if (participant === null || postFeedback === null) {
                // can't post feedback for this participant, skip it
                return null;
            }

            // if there's existing draft or published feedback (draft takes precedence) then check if it would change
            const relevantFeedback = getVisibleFeedback(participant);
            const hasValueToOverride = feedbackHasMeaningfulValue(relevantFeedback);
            if (hasValueToOverride && compareFeedback(relevantFeedback) === false) {
                // feedback is identical, so copying would not override it
                return null;
            }

            return { participant, postFeedback, relevantFeedback, hasValueToOverride };
        })
        .filter((student) => student !== null);
});

/** The number of target participants that have empty values */
const emptyCount = window.Vue.computed(() => targetParticipants.value.filter((p) => !p.hasValueToOverride).length);

/** The number of target participants that have values to override */
const overrideCount = window.Vue.computed(() => targetParticipants.value.filter((p) => p.hasValueToOverride).length);

/**
 * Copy the visible feedback to particular participants
 * @param targets The participants to copy to
 */
async function copyFeedbackToParticipants(targets: TargetParticipant[], targetLabel: string): Promise<void> {
    if (visibleFeedback.value === null) {
        return; // nothing to copy, shouldn't end up here
    }

    if (isCopying.value) {
        return; // already copying, don't allow another copy to start
    }

    emit("copying"); // close the popout at this point
    isCopying.value = true;

    /* close any open feedback drawer first: its editable state is bound to a single participant's
     * draft and would go stale (and could overwrite the copied drafts) once the copy runs
     */
    mbStore.setFeedbackSelectedCell(null);

    const fieldsPlural = targets.length === 1 ? "field" : "fields";
    const toast = window.sbProps.toastActivate(`Copying ${fieldLabel.value} to ${targetLabel} ${fieldsPlural}...`);

    let failures = 0;

    for (const target of targets) {
        // start with the existing draft feedback if it exists
        const body = {
            responseId: target.participant.marks.draft.responseId ?? null,
            mark: target.relevantFeedback?.hrInstructor ?? null,
            comment: target.relevantFeedback?.comment ?? null,
            resubmissionRequired: target.relevantFeedback?.resubmissionRequired ?? undefined,
        };

        // depending on the cell type, override the relevant field with the visible feedback
        if (props.cellType === MarkbookGridCellType.MARK) {
            body.mark = visibleFeedback.value.hrInstructor;
        } else if (props.cellType === MarkbookGridCellType.COMMENT) {
            body.comment = visibleFeedback.value.comment;
        } else {
            assertUnreachable(props.cellType);
        }

        const response = await apiRequest<{
            id: number; // Response/Attempt ID
            normalisedMark: number | null;
            humanReadableMark: string | null;
            humanReadableInstructorMark: string | null;
        }>({
            method: "POST",
            url: target.postFeedback,
            body,
        });

        if (!response.success) {
            failures += 1;
        } else {
            /* persist the new draft locally (rubrics are left untouched) */
            Object.assign(target.participant.marks.draft, {
                responseId: response.data.id,
                norm: response.data.normalisedMark,
                hr: response.data.humanReadableMark,
                hrInstructor: response.data.humanReadableInstructorMark,
                comment: body.comment,
                resubmissionRequired: body.resubmissionRequired,
            });
        }
    }

    if (failures === 0) {
        toast.timeout({
            text: `Copied ${fieldLabel.value} to ${targets.length - failures} ${fieldsPlural}`,
            isSuccess: true,
        });
    } else {
        toast.timeout({
            text: `Failed to copy ${fieldLabel.value} to ${failures} of ${targets.length} ${fieldsPlural}`,
            isSuccess: false,
        });
    }

    isCopying.value = false;
    isConfirmModalOpen.value = false;
}

/**
 * Copy the visible feedback to all empty fields (participants that don't have a meaningful value)
 */
async function copyToEmptyFields(): Promise<void> {
    const emptyTargets = targetParticipants.value.filter((p) => !p.hasValueToOverride);
    await copyFeedbackToParticipants(emptyTargets, "all empty");
}

/**
 * Copy the visible feedback to all fields (participants that have a meaningful value will be overridden)
 */
async function copyToAllFields(): Promise<void> {
    // If the confirm modal isn't already open, open it if there are meaningful values to override.
    if (!isConfirmModalOpen.value && overrideCount.value > 0) {
        isConfirmModalOpen.value = true;
        return;
    }

    await copyFeedbackToParticipants(targetParticipants.value, "all");
}
</script>

<template>
    <template v-if="hasValueToCopy">
        <pop-out-menu-divider />
        <pop-out-menu-item :class="{ 'is-disabled': emptyCount === 0 }" @selected="copyToEmptyFields">
            Copy to all empty {{ fieldLabel }} fields
        </pop-out-menu-item>
        <pop-out-menu-item :class="{ 'is-disabled': targetParticipants.length === 0 }" @selected="copyToAllFields">
            Copy to all {{ fieldLabel }} fields
        </pop-out-menu-item>
    </template>

    <!-- confirmation modal for the "copy ... to all" action -->
    <basic-modal v-if="isConfirmModalOpen" v-model:is-open="isConfirmModalOpen" :has-background-click="!isCopying">
        <h2>Please confirm</h2>

        <p>
            This will override
            {{ overrideCount }}
            {{ overrideCount === 1 ? "row that already contains" : "rows that already contain" }}
            data.
        </p>

        <template #footer>
            <div class="component-action">
                <section>
                    <button class="button" :disabled="isCopying" @click="isConfirmModalOpen = false">Cancel</button>
                    <button class="button" :disabled="isCopying" type="submit" @click.prevent="copyToAllFields">
                        {{ isCopying ? "Copying..." : "Copy" }}
                    </button>
                </section>
            </div>
        </template>
    </basic-modal>
</template>
