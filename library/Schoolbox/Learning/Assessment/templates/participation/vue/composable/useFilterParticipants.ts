import type { ComputedRef, Ref } from "vue";
import type { Participant } from "../../js/types/customParticipationTypes";

/**
 * Composable for filtering participants by customisation state and differentiation profile.
 * @param participants - Reactive list of participants.
 * @returns onlyShowDP, customisationFilter, customisationOptions, filteredParticipants
 */
export function useFilterParticipants(participants: Ref<Participant[]>): {
    onlyShowDP: Ref<boolean>;
    customisationFilter: Ref<string>;
    customisationOptions: { label: string; value: string }[];
    filteredParticipants: ComputedRef<Participant[]>;
} {
    const onlyShowDP = window.Vue.ref(false);
    const customisationFilter = window.Vue.ref("all");
    const customisationOptions = [
        { label: "Show All Students", value: "all" },
        { label: "Excluded", value: "excluded" },
        { label: "Modified Activity", value: "modified" },
        { label: "New Activity", value: "new" },
        { label: "No Customisation", value: "none" },
    ];

    const filteredParticipants = window.Vue.computed(() => {
        let filtered = participants.value;

        if (onlyShowDP.value) {
            filtered = filtered.filter((participant) => participant._links.differentiationProfile);
        }

        if (customisationFilter.value === "excluded") {
            filtered = filtered.filter(
                (participant) => participant.excludedAt != null && participant.customisedPlan == null,
            );
        }
        if (customisationFilter.value === "none") {
            filtered = filtered.filter(
                (participant) =>
                    participant.excludedAt == null &&
                    participant.customisedPlan == null &&
                    participant.plan?.name == null,
            );
        }
        if (customisationFilter.value === "new") {
            filtered = filtered.filter((participant) => participant.customisedPlan != null);
        }
        if (customisationFilter.value === "modified") {
            filtered = filtered.filter((participant) => participant.plan?.name != null);
        }

        return filtered;
    });

    return {
        onlyShowDP,
        customisationFilter,
        customisationOptions,
        filteredParticipants,
    };
}
