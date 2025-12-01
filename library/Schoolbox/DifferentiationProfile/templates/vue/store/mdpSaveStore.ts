/**
 * To save an entire row of the MDP editor
 */

import { defineStore } from "pinia";
import { apiRequest } from "@js/util/sbx/apiUtils";
import locale from "@js/terminology/rosettaStone";
import type { Profile, ProfileKeys } from "../../js/types/manageDifferentiationProfilesTypes";

/**
 * Type for save response
 */
type SaveResponse = {
    id: number;
    updated_at: string;
};

export const useSaveStore = defineStore(`saveStore`, () => {
    const isSaving = window.Vue.ref(false);
    const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create

    /**
     * Update the profile with the keys to save and return the body for the save request
     * @param profile - the profile to save
     * @param toSave - object containing specific keys to update in the profile
     * @returns - body to send in the save request
     */
    function getSaveBody<K extends ProfileKeys>(
        profile: Profile,
        toSave: Partial<Record<K, Profile[K]>>,
    ): Partial<Profile> {
        const body: Partial<Profile> = { ...profile };
        (Object.keys(toSave) as K[]).forEach((key) => {
            const value = toSave[key];
            if (value !== undefined) {
                body[key] = value;
            }
        });
        return body;
    }

    /**
     * Update a profile with the provided keys
     * @param profile - the profile to update
     * @param toSave - object containing specific keys to update in the profile
     */
    function updateProfile<K extends ProfileKeys>(profile: Profile, toSave: Partial<Record<K, Profile[K]>>): void {
        (Object.keys(toSave) as K[]).forEach((key) => {
            const value = toSave[key];
            if (value !== undefined) {
                profile[key] = value;
            }
        });
    }

    /**
     * Save the given student row
     * @param profile - the profile to save
     * @param toSave - object containing specific keys to update in the profile
     */
    async function save(profile: Profile, toSave: Partial<Pick<Profile, ProfileKeys>>): Promise<void> {
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
            const response = await apiRequest<SaveResponse, Partial<Profile>>({
                url: profile._routes.edit,
                method: "POST",
                body: getSaveBody(profile, toSave),
            });
            if (!response.success) {
                throw new Error(response.message || "Failed to save");
            }
            updateProfile(profile, { ...toSave, ...response.data });
            toast.timeout({ text: `${locale.differentiationProfile.proper()} successfully updated` });
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
        updateProfile,
    };
});
