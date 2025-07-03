import { debounce } from "lodash";
import type { Ref } from "vue";
import type { MarkScheme, SubmissionErrorStates } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";
import { normaliseHrMark } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";

interface DynamicFeedbackEditableData {
    hrMarkInstructor: Ref<string | null>;
    comment: Ref<string | null>;
    resubmissionRequired: Ref<boolean>;
}

interface DynamicFeedbackData extends DynamicFeedbackEditableData {
    hrMark: Ref<string | null>;
    normMark: Ref<number | null>;
}

interface DynamicFeedbackExtraData {
    hasSubmittedResponse: Ref<boolean>;
    hasPublishedFeedback: Ref<boolean>;
}

const TIMEOUT_DURATION_MS = 600; // After how many milliseconds should we auto-save user input

/**
 * A simplified version of jquery.dynamic-feedback.js
 * currently intended for usage by the markbooks / managing draft/published logic with these fields:
 * - grade
 * - resubmission required
 * - comment
 * @param data                     - Refs to each field shown to the user, these will be populated with the draft data if it exists, else the published data
 *                                   these should be used in a v-model or similar to allow the user to edit them
 * @param dataDraft                - Refs to each field that should be treated as the saved draft data state
 * @param dataPublished            - Refs to each field that should be treated as the saved published data state
 * @param dataExtra                - Extra refs to fields that get updated when the user does actions like publishing feedback
 * @param postModify               - See below:
 * @param postModify.route         - The route to post the modified data to
 * @param postModify.extraFormData - Any extra form data to include in the POST reques
 * @param isQuiz                   - Whether the activity is a quiz
 * @param instructorMarkScheme     - The instructor mark scheme
 * @param submissionErrorStates    - All the states from submission_errors
 * @returns properties
 */
// eslint-disable-next-line @typescript-eslint/explicit-function-return-type -- we want the inferred type from this as it's more useful
export function useDynamicFeedback(
    data: DynamicFeedbackEditableData,
    dataDraft: DynamicFeedbackData,
    dataPublished: DynamicFeedbackData,
    dataExtra: DynamicFeedbackExtraData,
    postModify: {
        route: string;
        extraFormData?: Record<string, unknown>;
    },
    isQuiz: boolean,
    instructorMarkScheme: MarkScheme,
    submissionErrorStates: SubmissionErrorStates,
) {
    /**
     * Is a given mark-set meaningful?
     * @param marks - The marks to check
     * @returns bool
     */
    function hasMeaningfulMarks(marks: DynamicFeedbackData): boolean {
        return (
            marks.hrMarkInstructor.value !== null || (marks.comment.value !== null && marks.comment.value.length > 0)
        );
    }

    const hasDraftMark = window.Vue.computed(() => hasMeaningfulMarks(dataDraft));
    const hasPublishedMark = window.Vue.computed(() => hasMeaningfulMarks(dataPublished));

    /**
     * Figure out if there's any initial data to use, and if so - populate the data with it
     * Draft takes precedence over published if both are available
     */
    let initialData: DynamicFeedbackData | null = null;
    if (hasDraftMark.value) {
        initialData = dataDraft;
    } else if (hasPublishedMark.value) {
        initialData = dataPublished;
    }

    if (initialData !== null) {
        data.hrMarkInstructor.value = initialData.hrMarkInstructor.value;
        data.comment.value = initialData.comment.value;
        data.resubmissionRequired.value = initialData.resubmissionRequired.value;
    }

    /**
     * Does the current draft state differ from the actual state?
     */
    const isDraftDirty = window.Vue.computed(
        (): boolean =>
            data.hrMarkInstructor.value !== dataDraft.hrMarkInstructor.value ||
            data.comment.value !== dataDraft.comment.value ||
            data.resubmissionRequired.value !== dataDraft.resubmissionRequired.value,
    );

    /**
     * Does the current published state differ from the draft state?
     */
    const isPublishedDirty = window.Vue.computed(
        (): boolean =>
            data.hrMarkInstructor.value !== dataPublished.hrMarkInstructor.value ||
            data.comment.value !== dataPublished.comment.value ||
            data.resubmissionRequired.value !== dataPublished.resubmissionRequired.value,
    );

    const isPublishInProgress = window.Vue.ref(false);
    const hasPublishError = window.Vue.ref(false);

    const isAutoSaveInProgress = window.Vue.ref(false);
    const isAutoSavePending = window.Vue.ref(false);

    const hasAutoSaveError = window.Vue.ref(false);
    const showDraftDialogue = window.Vue.ref(false);

    /**
     * @param isPublish - Whether the form data is being prepared for publishing or just saving a draft
     * @returns The current form data
     */
    function getFormData(isPublish: boolean): Record<string, unknown> {
        // If there's extra form data to send, include it
        // This will be the case if the activity is a quiz, since it needs to include the responseId and maxMark
        const body = postModify.extraFormData ?? {};

        body.mark = data.hrMarkInstructor.value;
        body.comment = data.comment.value;
        body.resubmissionRequired = data.resubmissionRequired.value;

        if (isPublish) {
            body.publish = "1"; /* This MUST be the string "1", not a boolean, lol */
        }

        return body;
    }

    /**
     * Save the draft feedback
     */
    async function autoSaveDraft(): Promise<void> {
        /* don't auto-save if we are currently publishing */
        if (isPublishInProgress.value) {
            return;
        }

        isAutoSaveInProgress.value = true;
        hasAutoSaveError.value = false;

        /* the first time a draft is saved, the "Draft saved"/"Saving failed"/etc dialogue should be shown */
        showDraftDialogue.value = true;

        const toast = window.sbProps.toastActivate("Autosaving...");

        try {
            const resp = await fetch(postModify.route, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                body: JSON.stringify(getFormData(false)),
            });

            if (!resp.ok) {
                throw new Error(`Failed to auto-save draft feedback: ${resp.status} ${resp.statusText}`);
            }

            toast.timeout({ text: "Draft saved", isSuccess: true });

            // persist the draft data
            dataDraft.normMark.value = normaliseHrMark(
                data.hrMarkInstructor.value,
                instructorMarkScheme,
                submissionErrorStates,
            );
            dataDraft.hrMarkInstructor.value = window.Vue.unref(
                data.hrMarkInstructor.value,
            ); /* TODO: This ideally un-normalises the mark */
            dataDraft.hrMark.value = window.Vue.unref(
                data.hrMarkInstructor.value,
            ); /* TODO: This also requires un-normalising the mark lol */
            dataDraft.comment.value = window.Vue.unref(data.comment.value);
            dataDraft.resubmissionRequired.value = window.Vue.unref(data.resubmissionRequired.value);
        } catch (error) {
            console.error("Error auto-saving draft feedback:", error);
            toast.timeout({ text: "Unable to save draft", isSuccess: false });
            isAutoSaveInProgress.value = false;
            hasAutoSaveError.value = true;
            return;
        }

        isAutoSaveInProgress.value = false;

        // Did the user make more changes while the draft auto-save was in progress?
        if (isAutoSavePending.value) {
            isAutoSavePending.value = false;
            await autoSaveDraft();
        }
    }

    /**
     * Callback invoked when the user changes feedback form input.
     * May auto-save a draft, or schedule an auto-save of a draft.
     */
    async function onFeedbackChanged(): Promise<void> {
        // Ignore if there's nothing to save
        if (!isDraftDirty.value) {
            return;
        }

        /**
         * If this is a quiz, we don't want to save drafts, but instead provide the option
         * to publish the feedback on change immediately.
         * So show the action bar and ensure the publish button is enabled, as it'll typically
         * only be enabled if the draft has been saved otherwise.
         */
        if (isQuiz) {
            // Action bar is handled by the parent component, so nothing to do here
            return;
        }

        // Is an auto-save in progress?
        if (isAutoSaveInProgress.value) {
            // Don't overlap requests, but indicate we have unsaved changes that must be auto-saved
            isAutoSavePending.value = true;
            return;
        }

        // No auto-saves currently queued for participant - so initiate a draft auto-save
        await autoSaveDraft();
    }

    /**
     * When the user changes any of the fields, make a draft accordingly
     * For the comment, save after a timeout to avoid rapidly saving on every keystroke
     * For the mark and resubmissionRequired, save immediately
     */
    const onFeedbackChangedDebounced = debounce(onFeedbackChanged, TIMEOUT_DURATION_MS);
    window.Vue.watch(() => data.comment.value, onFeedbackChangedDebounced);
    window.Vue.watch(() => [data.hrMarkInstructor.value, data.resubmissionRequired.value], onFeedbackChanged);

    /**
     * Publish the feedback
     * @returns whether the publish was successful
     */
    async function publishFeedback(): Promise<boolean> {
        if (isPublishInProgress.value) {
            return false;
        }

        isPublishInProgress.value = true;
        hasPublishError.value = false;

        /* Now that we're publishing, we don't want to accidentally auto-save the draft after this point */
        isAutoSavePending.value = false;
        showDraftDialogue.value = false;
        onFeedbackChangedDebounced.cancel();

        const toast = window.sbProps.toastActivate("Publishing...");

        try {
            const resp = await fetch(postModify.route, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                body: JSON.stringify(getFormData(true)),
            });

            if (!resp.ok) {
                throw new Error(`Failed to publish feedback: ${resp.status} ${resp.statusText}`);
            }

            toast.timeout({ text: "Feedback published", isSuccess: true });

            dataExtra.hasPublishedFeedback.value = true;

            /* persist the published data */
            dataPublished.normMark.value = normaliseHrMark(
                data.hrMarkInstructor.value,
                instructorMarkScheme,
                submissionErrorStates,
            );
            dataPublished.hrMarkInstructor.value = window.Vue.unref(
                data.hrMarkInstructor,
            ); /* TODO: This ideally un-normalises the mark */
            dataPublished.hrMark.value = window.Vue.unref(
                data.hrMarkInstructor,
            ); /* TODO: This also requires un-normalising the mark lol */
            dataPublished.comment.value = window.Vue.unref(data.comment);
            dataPublished.resubmissionRequired.value = window.Vue.unref(data.resubmissionRequired);

            /* and reset the draft data to defaults */
            dataDraft.comment.value = null;
            dataDraft.hrMark.value = null;
            dataDraft.normMark.value = null;
            dataDraft.hrMarkInstructor.value = null;
            // notable ommission of resubmissionRequired, since it's actually the same ref
        } catch (error) {
            console.error("Error publishing feedback:", error);
            toast.timeout({ text: "Unable to publish feedback", isSuccess: false });
            isPublishInProgress.value = false;
            hasPublishError.value = true;
            return false;
        }

        isPublishInProgress.value = false;

        /* Just in case another draft auto-save was pending, clear it too */
        isAutoSavePending.value = false;
        showDraftDialogue.value = false;
        onFeedbackChangedDebounced.cancel();

        return true;
    }

    return {
        data,
        dataDraft,
        dataPublished,
        hasDraftMark,
        hasPublishedMark,
        isDraftDirty,
        isPublishedDirty,
        isAutoSaveInProgress,
        isAutoSavePending,
        isPublishInProgress,
        hasPublishError,
        hasAutoSaveError,
        showDraftDialogue,
        publishFeedback,
    };
}
