import { defineStore } from "pinia";

export const useActivitiesListGlobalStore = defineStore(`activitiesListGlobalStore`, () => {
    const ltiAssessmentsEnabled = window.Vue.ref<boolean>(false);

    /**
     * store any passed through properties
     * @param { boolean } $ltiAssessmentsEnabled - whether LTI assessments are enabled
     */
    function init($ltiAssessmentsEnabled: boolean) {
        ltiAssessmentsEnabled.value = $ltiAssessmentsEnabled;
    }

    return {
        init,
        ltiAssessmentsEnabled,
    };
});
