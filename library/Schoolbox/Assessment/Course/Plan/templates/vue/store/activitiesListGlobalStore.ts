import { defineStore } from "pinia";
import Toast from "@schoolboxVue/components/toast.vue";
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
    const toastEl = window.Vue.ref<typeof Toast>();

    /**
     * store any passed through properties
     * @param { ActivityTypeLocale } $activityTypeLocale - the locales for activity types
     * @param { boolean } $ltiAssessmentsEnabled - whether LTI assessments are enabled
     * @param { typeof Toast } $toastEl - the toast element
     */
    function init($activityTypeLocale: ActivityTypeLocale, $ltiAssessmentsEnabled: boolean, $toastEl: typeof Toast) {
        locale.value = $activityTypeLocale;
        ltiAssessmentsEnabled.value = $ltiAssessmentsEnabled;
        toastEl.value = $toastEl;
    }

    return {
        init,
        locale,
        ltiAssessmentsEnabled,
        toastEl,
    };
});
