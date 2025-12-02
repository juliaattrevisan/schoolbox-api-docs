import type { Ref } from "vue";
import type { Participant } from "../../js/types/customParticipationTypes";

/**
 * Composable for performing actions on participants with interaction checking
 * @param selectedList - Reactive ref to the currently selected participants
 * @param excludeParticipants - Function to exclude participants
 * @param includeParticipants - Function to include participants
 * @param includeParticipantInExistingPlan - Function to include participants in existing plans
 * @returns isConfirmModifyOpen, participantsWithInteractions, onActionSelected, onConfirmActionSelected
 */
export function usePerformAction(
    selectedList: Ref<Participant[]>,
    excludeParticipants: (participants: Participant[], selectedList?: Ref<Participant[]>) => Promise<void>,
    includeParticipants: (participants: Participant[]) => Promise<void>,
    includeParticipantInExistingPlan: () => Promise<void>,
): {
    isConfirmModifyOpen: Ref<boolean>;
    participantsWithInteractions: Ref<Participant[]>;
    onActionSelected: (participants: Participant[], action: string, $isSingleModify: boolean) => void;
    onConfirmActionSelected: () => Promise<void>;
} {
    const isConfirmModifyOpen = window.Vue.ref(false);
    const modifyAction = window.Vue.ref<string>("");
    const modifyList = window.Vue.ref<Participant[]>([]); // array of participants to modify
    const isSingleModify = window.Vue.ref(false); // whether the current modification is for a single participant (three-dot) or multiple (bottom drawer)
    const participantsWithInteractions = window.Vue.ref<Participant[]>([]); // participants with interactions in the current modification list

    /**
     * Performs the action for participants without interactions,
     * or after confirmation when selection contains participants with interactions.
     */
    async function performAction(): Promise<void> {
        switch (modifyAction.value) {
            case "removeCustomisation":
                await includeParticipants(modifyList.value);
                break;
            case "exclude":
                await excludeParticipants(modifyList.value);
                break;
            case "includeInExisting":
                await includeParticipantInExistingPlan();
                break;
            default:
                // redirect to the appropriate URL
                window.location.href = modifyAction.value;
        }
        if (!isSingleModify.value) {
            // only clear the selection if this is a multi-selection action
            selectedList.value = [];
        }
        modifyList.value = [];
        isConfirmModifyOpen.value = false; // close the confirmation modal
    }

    /**
     * Handles the modification of a selected participant(s).
     * If the participant(s) has interactions, it opens a confirmation modal.
     * Otherwise, the appropriate action is taken directly.
     * @param participants - The participants to modify.
     * @param action - the action to perform (e.g., 'removeCustomisation').
     * @param $isSingleModify - whether this action is for a single participant or multiple
     */
    function onActionSelected(participants: Participant[], action: string, $isSingleModify: boolean): void {
        modifyList.value = participants;
        modifyAction.value = action;
        isSingleModify.value = $isSingleModify;
        // if any participant has interactions, show the confirmation modal
        if (modifyList.value.some((participant) => participant.hasInteraction)) {
            participantsWithInteractions.value = modifyList.value.filter((participant) => participant.hasInteraction);
            isConfirmModifyOpen.value = true;
            return;
        }
        // otherwise, perform the action directly
        void performAction();
    }

    /**
     * Handles the confirmation of an action in the confirmation modal,
     * closes the modal after performing the action.
     */
    async function onConfirmActionSelected(): Promise<void> {
        await performAction();
    }

    return {
        isConfirmModifyOpen,
        participantsWithInteractions,
        onActionSelected,
        onConfirmActionSelected,
    };
}
