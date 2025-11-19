/**
 * To save an entire row of the MDP editor
 */

import { defineStore } from "pinia";
import { apiRequest } from "@js/util/sbx/apiUtils";
import locale from "@js/terminology/rosettaStone";
import type { Profile } from "../../js/types/manageDifferentiationProfilesTypes";

export const useSaveStore = defineStore(`saveStore`, () => {
    const isSaving = window.Vue.ref(false);
    const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create

    /**
     * Save the given student row
     * @param profile - the profile to save
     */
    async function save(profile: Profile): Promise<void> {
        if (isSaving.value) {
            return;
        }
        if (profile._routes.edit === undefined) {
            loadError.value = "Edit route is undefined";
            return;
        }
        const toast = window.sbProps.toastActivate("Saving...");
        try {
            isSaving.value = true;
            loadError.value = null;
            const response = await apiRequest({
                url: profile._routes.edit,
                method: "POST",
                body: profile,
            });
            if (!response.success) {
                throw new Error(response.message || "Failed to save");
            }
            toast.timeout({ text: `${locale.differentiationProfile.proper()} successfully updated` });
            // the date in the format of for example 24/05/2024
            profile.updated_at = window.dayjs(new Date()).format("DD/MM/YYYY");
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : "There was an unexpected error";
            loadError.value = errorMessage;
            toast.timeout({ text: errorMessage, isSuccess: false });
        } finally {
            isSaving.value = false;
        }
    }

    return {
        save,
    };
});
