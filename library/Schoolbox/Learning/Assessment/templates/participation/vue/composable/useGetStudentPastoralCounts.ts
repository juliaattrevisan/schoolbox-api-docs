import { hasErrorMessage, isAbortError } from "@js/util/errorUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { StudentPastoralCountBadge } from "@schoolboxLibrary/Pastoral/types/StudentPastoralCountBadge";
import type { Participant } from "../../js/types/customParticipationTypes";

/**
 * Composable for fetching and managing student pastoral counts
 */
export function useGetStudentPastoralCounts(
    pastoralEnabled: boolean,
    getStudentsPastoralCountsUrl: string,
    allParticipants: () => Participant[],
) {
    const loadError = window.Vue.ref<string | null>(null);
    const studentPastoralCounts = window.Vue.ref<Record<string, StudentPastoralCountBadge[]>>({});

    /**
     * Fetch the pastoral counts for the students
     */
    async function getStudentPastoralCounts(): Promise<void> {
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
            const responseJson = (await response.json()) as Record<string, StudentPastoralCountBadge[]>;
            if (typeof responseJson !== "object" || responseJson === null) {
                throw new Error("Unexpected response format");
            }
            if (typeof responseJson === "object" && responseJson !== null) {
                // get data
                studentPastoralCounts.value = responseJson;
            } else {
                throw new Error("Unexpected response format");
            }
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
     * Load the student pastoral counts when the component is mounted.
     */
    window.Vue.onMounted(() => {
        void getStudentPastoralCounts();
    });

    return {
        loadError,
        studentPastoralCounts,
        getStudentPastoralCounts,
    };
}
