import { defineStore } from "pinia";
import Toast from "@schoolboxVue/components/toast.vue";
import type { ActivityTypeLocale, ActivitiesListRoutes } from "@schoolboxAssessment/Types/assessmentTypes";

export const useActivitiesListGlobalStore = defineStore(`activitiesListGlobalStore`, () => {
    const activityTypeLocale = window.Vue.ref<ActivityTypeLocale>();
    const activitiesListRoutes = window.Vue.ref<ActivitiesListRoutes>();
    const ltiAssessmentsEnabled = window.Vue.ref<boolean>(false);
    const toastEl = window.Vue.ref<typeof Toast>();

    /**
     * store any passed through properties
     * @param { ActivityTypeLocale } $activityTypeLocale - the locales for activity types
     * @param { ActivitiesListRoutes } $activitiesListRoutes - the routes relevant to actions on an activities list
     * @param { boolean } $ltiAssessmentsEnabled - whether LTI assessments are enabled
     * @param { typeof Toast } $toastEl - the toast element
     */
    function init(
        $activityTypeLocale: ActivityTypeLocale,
        $activitiesListRoutes: ActivitiesListRoutes,
        $ltiAssessmentsEnabled: boolean,
        $toastEl: typeof Toast,
    ) {
        activityTypeLocale.value = $activityTypeLocale;
        activitiesListRoutes.value = $activitiesListRoutes;
        ltiAssessmentsEnabled.value = $ltiAssessmentsEnabled;
        toastEl.value = $toastEl;
    }

    return {
        init,
        activityTypeLocale,
        activitiesListRoutes,
        ltiAssessmentsEnabled,
        toastEl,
    };
});
