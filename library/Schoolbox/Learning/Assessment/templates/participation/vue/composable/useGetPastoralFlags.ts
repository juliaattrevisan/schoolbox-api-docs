import type { Ref } from "vue";
import { hasErrorMessage, isAbortError } from "@js/util/errorUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { PastoralFlag } from "@schoolboxLibrary/Pastoral/types/PastoralFlag";
import type { Participant } from "../../js/types/customParticipationTypes";

/**
 * Composable for fetching and managing student pastoral flags
 * @param pastoralEnabled - Whether the pastoral feature is enabled
 * @param getStudentsPastoralCountsUrl - The URL template to fetch student pastoral flags
 * @param allParticipants - Function that returns the list of all participants
 * @returns loadError, pastoralFlags, getStudentPastoralFlags
 */
export function useGetPastoralFlags(
    pastoralEnabled: boolean,
    getStudentsPastoralCountsUrl: string,
    allParticipants: () => Participant[],
): {
    loadError: Ref<string | null>;
    pastoralFlags: Ref<Record<string, PastoralFlag[]>>;
    getStudentPastoralFlags: () => Promise<void>;
} {
    const loadError = window.Vue.ref<string | null>(null);
    const pastoralFlags = window.Vue.ref<Record<string, PastoralFlag[]>>({});

    /**
     * Fetch the pastoral flags for the students
     */
    async function getStudentPastoralFlags(): Promise<void> {
        if (!pastoralEnabled || getStudentsPastoralCountsUrl === "") {
            return;
        }
        const url = getStudentsPastoralCountsUrl.replace(
            "{studentIds}",
            allParticipants()
                .map((participant) => participant.user.id)
                .join(","),
        );
        try {
            const response = await fetch(url, { headers: { Accept: "application/json" } });
            if (!response.ok) {
                const errorMessage = await extractErrorMessage(response);
                throw new Error(errorMessage);
            }
            const responseJson = (await response.json()) as Record<string, PastoralFlag[]>;
            if (typeof responseJson !== "object" || responseJson === null) {
                throw new Error("Unexpected response format");
            }
            pastoralFlags.value = responseJson;
        } catch (error) {
            // ignore abort errors, as the previous report was cancelled
            if (isAbortError(error)) {
                return;
            }
            if (error instanceof Error && hasErrorMessage(error)) {
                loadError.value = error.message;
            }
            console.error(error);
        }
    }

    /**
     * Load the student pastoral flags when the component is mounted.
     */
    window.Vue.onMounted(() => {
        void getStudentPastoralFlags();
    });

    return {
        loadError,
        pastoralFlags,
        getStudentPastoralFlags,
    };
}
