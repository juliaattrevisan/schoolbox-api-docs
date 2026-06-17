import type { Ref } from "vue";
import { postIncludeExistingParticipant } from "../../js/types/customParticipationPayload";
import type { CustomisedPlan, OptionalRoute, Participant, Plan } from "../../js/types/customParticipationTypes";

/**
 * Composable for including participants in existing customisations
 * @param loadError - Reactive ref to store any loading errors
 * @param customisations - List of available customisations (plans and customised plans)
 * @param includeInExistingCustomisationUrl - The URL to post the inclusion request to
 * @param selectedList - Reactive ref to the currently selected participants
 * @returns isIncludeInExistingOpen, includeInExistingList, includeInExistingActivityId, isSingleIncludeInExisting, includeParticipantInExistingPlan, openIncludeInExistingModal
 */
export function useIncludeInExisting(
    loadError: Ref<string | null>,
    customisations: (Plan | CustomisedPlan)[],
    includeInExistingCustomisationUrl: OptionalRoute,
    selectedList: Ref<Participant[]>,
): {
    isIncludeInExistingOpen: Ref<boolean>;
    includeInExistingList: Ref<Participant[]>;
    includeInExistingActivityId: Ref<number | null>;
    isSingleIncludeInExisting: Ref<boolean>;
    includeParticipantInExistingPlan: () => Promise<void>;
    openIncludeInExistingModal: (isSingle: boolean, participant: Participant | null) => void;
} {
    const isIncludeInExistingOpen = window.Vue.ref(false);
    const includeInExistingList = window.Vue.ref<Participant[]>([]); // array of participants to include in existing customisation
    const includeInExistingActivityId = window.Vue.ref<number | null>(null);
    const isSingleIncludeInExisting = window.Vue.ref(false); // whether the include is for a single participant (three-dot) or multiple (bottom drawer)

    /**
     * Add all the selected participants to an existing customisation plan.
     */
    async function includeParticipantInExistingPlan(): Promise<void> {
        if (!includeInExistingCustomisationUrl) return;
        if (includeInExistingActivityId.value === null) return;
        const activity = customisations.find((plan) => plan.id === includeInExistingActivityId.value);
        if (!activity) return;
        const toast = window.sbProps.toastActivate("Adding to customisation...");
        const response = await postIncludeExistingParticipant(includeInExistingCustomisationUrl, {
            students: includeInExistingList.value.map((p) => p.user.id),
            ...("assessable" in activity ? { assessmentId: activity.assessable.id } : {}),
            ...(activity.name !== null ? { planId: activity.id } : {}),
            includeResponse: true,
        });
        if (!response.success) {
            const error = response.message ?? "";
            loadError.value = error;
            console.error("Error adding Customisation:", error);
            toast.timeout({ text: error, isSuccess: false });
            return;
        }
        const { data } = response;
        // add the activity to each participant
        includeInExistingList.value.forEach((participant) => {
            if ("assessable" in activity) {
                participant.customisedPlan = { ...activity };
                participant.hasInteraction = data?.hasInteraction?.[participant.user.id] ?? false;
            } else {
                participant.plan = { ...activity };
                participant.hasInteraction = data?.hasInteraction?.[participant.user.id] ?? false;
            }
        });
        toast.timeout({ text: "Customisation added successfully", isSuccess: true });
        isIncludeInExistingOpen.value = false;
        includeInExistingList.value = [];
    }

    /**
     * Opens the modal to include a participant in an existing customisation.
     * @param isSingle - Whether this is a single participant inclusion (from pop-out) or multiple (from bottom drawer).
     * @param participant - The participant to be included.
     */
    function openIncludeInExistingModal(isSingle: boolean, participant: Participant | null): void {
        isSingleIncludeInExisting.value = isSingle;
        if (isSingle && participant !== null) {
            includeInExistingList.value = [participant];
        } else {
            includeInExistingList.value = selectedList.value;
        }
        includeInExistingActivityId.value = null;
        isIncludeInExistingOpen.value = true;
    }

    return {
        isIncludeInExistingOpen,
        includeInExistingList,
        includeInExistingActivityId,
        isSingleIncludeInExisting,
        includeParticipantInExistingPlan,
        openIncludeInExistingModal,
    };
}
