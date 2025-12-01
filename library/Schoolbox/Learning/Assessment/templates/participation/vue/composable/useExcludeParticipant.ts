import type { Ref } from "vue";
import { hasErrorMessage } from "@js/util/errorUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { Participant } from "../../js/types/customParticipationTypes";

/**
 * Composable for excluding participants from activities
 * @param loadError - Reactive ref to store any loading errors
 * @returns - excludeParticipant, excludeParticipants
 */
export function useExcludeParticipant(loadError: Ref<string | null>): {
    excludeParticipant: (participant: Participant) => Promise<void>;
    excludeParticipants: (
        participants: Participant[],
        selectedList?: ReturnType<typeof window.Vue.ref<Participant[]>>,
    ) => Promise<void>;
} {
    /**
     * Excludes a participant.
     * @param participant - The participant to exclude.
     * @returns A promise that resolves when the operation is complete.
     */
    async function excludeParticipant(participant: Participant): Promise<void> {
        const toast = window.sbProps.toastActivate("Excluding...");
        try {
            const response = await fetch(participant._links.exclude, {
                method: "POST",
                headers: { Accept: "application/json", "Content-Type": "application/json" },
            });
            if (!response.ok) {
                const errorMessage = await extractErrorMessage(response);
                throw new Error(errorMessage);
            }
            participant.excludedAt = new Date().toISOString(); // Assigning a timestamp as a string
            participant.hasInteraction = false;
            toast.timeout({ text: "Student excluded successfully" });
        } catch (error) {
            if (error instanceof Error && hasErrorMessage(error)) {
                loadError.value = error.message;
            }
            console.error("Error excluding participant:", error);
            toast.timeout({ text: "Error excluding student", isSuccess: false });
        }
    }

    /**
     * Excludes the selected participants.
     * @param participants - The participants to exclude.
     * @returns A promise that resolves when all exclusions are complete.
     */
    async function excludeParticipants(participants: Participant[]): Promise<void> {
        try {
            await Promise.all(participants.map((participant) => excludeParticipant(participant)));
        } catch (error) {
            if (error instanceof Error && hasErrorMessage(error)) {
                loadError.value = error.message;
            }
            console.error("Error excluding selected participants:", error);
        }
    }

    return {
        excludeParticipant,
        excludeParticipants,
    };
}
