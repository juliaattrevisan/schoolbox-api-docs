import { postIncludeParticipant } from "../../js/types/customParticipationPayload";
import type { Participant } from "../../js/types/customParticipationTypes";

/**
 * Composable for including participants in activities
 */
export function useIncludeParticipant(loadError: ReturnType<typeof window.Vue.ref<string | null>>) {
    /**
     * Includes a participant.
     * @param participant - The participant to include.
     * @returns A promise that resolves when the operation is complete.
     */
    async function includeParticipant(participant: Participant): Promise<void> {
        const toast = window.sbProps.toastActivate("Removing customisation...");
        const response = await postIncludeParticipant(participant._links.include, {
            includeResponse: true,
        });

        if (!response.success) {
            const error = response.message ?? "";
            loadError.value = error;
            console.error("Error including participant:", error);
            toast.timeout({ text: error, isSuccess: false });
            return;
        }

        participant.excludedAt = null; // Resetting the exclusion timestamp
        participant.plan.name = null; // Resetting the plan
        participant.customisedPlan = null; // Resetting the customised plan
        participant.hasInteraction = response.data?.hasInteraction ?? false;
        toast.timeout({ text: "Customisation removed successfully" });
    }

    /**
     * Includes the selected participants.
     * @param participants - The participants to include.
     * @returns A promise that resolves when all inclusions are complete.
     */
    async function includeParticipants(participants: Participant[]): Promise<void> {
        await Promise.all(participants.map((participant) => includeParticipant(participant)));
    }

    return {
        includeParticipant,
        includeParticipants,
    };
}