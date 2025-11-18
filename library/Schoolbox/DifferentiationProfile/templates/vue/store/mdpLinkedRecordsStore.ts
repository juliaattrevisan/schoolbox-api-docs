/**
 * To hold the header data for the MDP editor
 */

import { defineStore } from "pinia";
import type { Profile } from "../../js/types/manageDifferentiationProfilesTypes";

export const useLinkedRecordsStore = defineStore(`linkedRecordsStore`, () => {
    const url = window.Vue.ref<string>("");
    const title = window.Vue.ref<string>("");
    const meta = window.Vue.ref<string>("");

    /**
     * When linked records is selected for a profile
     * @param profile - the profile to view linked records for
     */
    function select(profile: Profile): void {
        if (profile._routes?.linkedRecords) {
            title.value = `${profile.student.fullName} - Linked Records`;
            url.value = profile._routes.linkedRecords;
        }
    }

    return {
        url,
        title,
        meta,
        select,
    };
});
