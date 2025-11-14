/**
 * To save an entire row of the MDP editor
 */

import { defineStore } from "pinia";
import { apiRequest } from "@js/util/sbx/apiUtils";

export const useMdpSaveStore = defineStore(`mdpSaveStore`, () => {
    const isSaving = window.Vue.ref(false);
    const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create

    /**
     * Save the given student row
     * @param $student - the student object for the row
     */
    async function save(): Promise<void> {
        if (isSaving.value) {
            return;
        }
        const toast = window.sbProps.toastActivate("Saving...");
        try {
            isSaving.value = true;
            loadError.value = null;
            const response = await apiRequest({
                url: props.postRoute,
                method: "POST",
                body: {
                    enabled: data.value.featureToggle.enabled ? 1 : 0,
                    modules: data.value.modules,
                },
            });
            if (!response.success) {
                throw new Error(response.message || "Failed to save");
            }
            toast.timeout({ text: "Differentiation profiles successfully updated" });
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
