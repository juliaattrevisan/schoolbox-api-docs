import { defineStore } from "pinia";
import type { ActivityTypeLocale } from "@schoolboxAssessment/Types/assessmentTypes";

export const useActivitiesListGlobalStore = defineStore(`activitiesListGlobalStore`, () => {
    const locale = window.Vue.ref<ActivityTypeLocale>({
        project: {
            proper: "Project",
        },
        dueWork: {
            proper: "Due Work",
        },
    });
    const ltiAssessmentsEnabled = window.Vue.ref<boolean>(false);

    /**
     * store any passed through properties
     * @param { ActivityTypeLocale } $activityTypeLocale - the locales for activity types
     * @param { boolean } $ltiAssessmentsEnabled - whether LTI assessments are enabled
     */
    function init($activityTypeLocale: ActivityTypeLocale, $ltiAssessmentsEnabled: boolean) {
        locale.value = $activityTypeLocale;
        ltiAssessmentsEnabled.value = $ltiAssessmentsEnabled;
    }

    return {
        init,
        locale,
        ltiAssessmentsEnabled,
    };
});
