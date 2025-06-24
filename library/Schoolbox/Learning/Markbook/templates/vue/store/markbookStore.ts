import { defineStore } from "pinia";
import type { Markbook, MarkbookActivity } from "../types/payload";

export const useMarkbookStore = defineStore("markbookStore", () => {
    const endpoint = window.Vue.ref<string>("");
    const data = window.Vue.ref<Markbook | null>(null);

    const showDisplayMarks = window.Vue.ref(false);
    const showDrafts = window.Vue.ref(false);
    const showAverages = window.Vue.ref(false);

    /**
     * Fetch markbook data
     */
    async function fetchMarkbook(): Promise<void> {
        if (!endpoint.value) {
            throw new Error("No markbook endpoint provided");
        }

        // todo: add error handling and filters support...
        const response = await fetch(endpoint.value, {
            method: "POST",
        });

        data.value = (await response.json()) as Markbook;
    }

    /**
     * Initialise the store
     * @param $endpoint - The endpoint for the markbook data
     */
    async function init($endpoint: string): Promise<void> {
        endpoint.value = $endpoint;
        await fetchMarkbook();
    }

    /**
     * For the course markbook, we show headers for the unit spec groups of assessments
     * This groups all the assessments into groups of unit specs
     */
    const activityGroups = window.Vue.computed(() => {
        if (data.value === null) {
            return [];
        }

        /**
         * Assessments are currently ordered by the backend based on a provided sequence,
         * but this is the point to change that if needed
         */
        const assessments = [...Object.values(data.value.assessments)].sort((left, right) => {
            return left.sequence - right.sequence;
        });

        const groups: {
            unitSpec?: MarkbookActivity["unitSpec"];
            activities: MarkbookActivity[];
        }[] = [];

        assessments.forEach((activity) => {
            let group = groups.find((g) => g.unitSpec?.id === activity.unitSpec?.id);
            if (!group) {
                group = {
                    unitSpec: activity.unitSpec,
                    activities: [],
                };
                groups.push(group);
            }

            group.activities.push(activity);
        });

        return groups;
    });

    /**
     * The source of truth for the sequence to display activities in,
     * the actual sorting occurs in activityGroups above since that also determines the sort order of the unit specs
     */
    const orderedActivities = window.Vue.computed(() => activityGroups.value.flatMap((group) => group.activities));

    return {
        init,
        data,
        showDisplayMarks,
        showDrafts,
        showAverages,
        activityGroups,
        orderedActivities,
    };
});
