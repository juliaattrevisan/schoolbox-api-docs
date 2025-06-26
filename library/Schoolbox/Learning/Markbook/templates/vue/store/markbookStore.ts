import { defineStore } from "pinia";
import { listenForFilterLoad, listenForFilterLoading } from "@schoolboxLibrary/Filter/templates/ts/filterEvent";
import type {
    Markbook,
    MarkbookActivity,
    MarkbookClassImpl,
    MarkbookClassImplActivity,
    MarkbookPopoutSelectedCellInfo,
    MarkbookFeedbackSelectedCellInfo,
} from "../types/payload";

export const useMarkbookStore = defineStore("markbookStore", () => {
    const data = window.Vue.ref<Markbook | null>(null);

    const showDisplayMarks = window.Vue.ref(false);
    const showDrafts = window.Vue.ref(false);
    const showAverages = window.Vue.ref(false);

    const popoutSelectedCell = window.Vue.ref<MarkbookPopoutSelectedCellInfo | null>(null);
    const feedbackSelectedCell = window.Vue.ref<MarkbookFeedbackSelectedCellInfo | null>(null);

    /**
     * If the markbook has more than this number of participants,
     * reduce functionality to improve browser performance (SA-5714)
     */
    const functionDegradeLimit = 80;
    const isDegraded = window.Vue.computed(() => {
        if (data.value === null) {
            return 0;
        }

        const students = data.value.classImpls.reduce((acc, impl) => acc + Object.keys(impl.students).length, 0);
        return students > functionDegradeLimit;
    });

    /**
     * When we start loading new markbood data, we reset the data to null to show the loading state
     */
    function onLoading(): void {
        data.value = null; // Reset the data to show loading state
    }

    /**
     * When the markbook data has successfully loaded
     * @param markbook - The markbook data
     */
    function onMarkbookLoaded(markbook: Markbook): void {
        data.value = markbook;
    }

    /**
     * Initialise the store
     * @param filterKey - The key of the filter to listen for loading events
     */
    function init(filterKey: string): void {
        /**
         * Listen to the filter overlords which control what we show and when we show it
         */
        listenForFilterLoading(filterKey, onLoading);
        listenForFilterLoad<Markbook>(filterKey, (detail) => onMarkbookLoaded(detail.content));
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

    /**
     * Retrieve an ordered list of activities for a class implementation
     * This leaves null's where the activity exists within orderedActivities and not the class impl
     * @param classImpl - The class implementation
     * @returns sequenced activities/null's
     */
    function orderedClassImplActivities(classImpl: MarkbookClassImpl): {
        activity: MarkbookActivity;
        aImpl: MarkbookClassImplActivity | null;
    }[] {
        return orderedActivities.value.map((activity) => ({
            activity,
            aImpl: classImpl.assessments[activity.id] ?? null,
        }));
    }

    /**
     * Retrieve an ordered list of students for a class implementation
     * This is the source of truth for the order of students in the markbook
     * @param classImpl - The class implementation
     * @returns ordered students
     */
    function orderedClassImplStudents(classImpl: MarkbookClassImpl): MarkbookClassImpl["students"][number][] {
        // alphabetical order by lastName then givenName
        return Object.values(classImpl.students).sort(
            (a, b) => a.lastName.localeCompare(b.lastName) || a.givenName.localeCompare(b.givenName),
        );
    }

    return {
        init,
        data,
        isDegraded,
        showDisplayMarks,
        showDrafts,
        showAverages,
        activityGroups,
        orderedActivities,
        orderedClassImplActivities,
        orderedClassImplStudents,
        popoutSelectedCell,
        feedbackSelectedCell,
    };
});
