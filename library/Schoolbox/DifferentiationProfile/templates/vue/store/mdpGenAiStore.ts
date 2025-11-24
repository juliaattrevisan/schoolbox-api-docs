/**
 * To manage the selection of generating AI content for a profile
 */

import { defineStore } from "pinia";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { isAbortError } from "@js/util/errorUtils";
import type { Profile, ProfileTextKeys } from "../../js/types/manageDifferentiationProfilesTypes";
import { useHeaderStore } from "./mdpHeaderStore";

/**
 * Type for individual AI response item
 */
type AiResponseItem = {
    response: string | null;
    title: string;
    key: ProfileTextKeys;
};

/**
 * Type for AI response array containing response items with titles
 */
type AiResponse = AiResponseItem[];

export const useGenAiStore = defineStore(`genAiStore`, () => {
    const headerStore = useHeaderStore();
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create
    const profile = window.Vue.ref<Profile | null>(null); // the profile to generate AI content for
    const aiResponse = window.Vue.ref<AiResponse | null>(null); // to store the AI generated content array
    let controller: AbortController; // to allow aborting the previous search if a new one is started

    /**
     * Filter response data to only include fields that are enabled in the header store
     * @param responseData - The AI response data to filter
     * @returns Array of filtered data containing only enabled fields with their titles
     */
    function filterEnabledFields(responseData: Partial<Pick<Profile, ProfileTextKeys>>): AiResponse {
        const enabledFields = headerStore.enabledFields;
        const filteredData: AiResponse = [];
        enabledFields.forEach((field) => {
            const { key } = field as { key: ProfileTextKeys };
            if (key in responseData && responseData[key] !== undefined) {
                filteredData.push({
                    response: responseData[key] || null,
                    title: field.title,
                    key: key,
                });
            }
        });
        return filteredData;
    }

    /**
     * Generate AI content for the selected profile
     */
    async function generate(): Promise<void> {
        if (isProcessing.value || profile.value === null) {
            return;
        }
        // if (profile._routes.edit === undefined) {
        //     loadError.value = "AI route is undefined";
        //     return;
        // }
        try {
            isProcessing.value = true;
            loadError.value = null;
            aiResponse.value = null;
            controller = new AbortController();
            const response = await apiRequest<Partial<Pick<Profile, ProfileTextKeys>>, { studentId: number }>({
                url: "/openai/differentiation-profile",
                method: "POST",
                signal: controller.signal,
                body: { studentId: profile.value.student.id },
            });
            if (!response.success) {
                throw new Error(response.message || "Failed to generate");
            }
            aiResponse.value = filterEnabledFields(response.data);
        } catch (error) {
            // ignore abort errors, as the previous request was cancelled
            if (isAbortError(error)) {
                return;
            }
            const errorMessage = error instanceof Error ? error.message : "There was an unexpected error";
            loadError.value = errorMessage;
        } finally {
            isProcessing.value = false;
        }
    }

    /**
     * to display a prompt to generate AI content for a profile
     * @param $profile - the profile to generate content for
     */
    function prompt($profile: Profile): void {
        profile.value = $profile;
        isOpen.value = true;
        void generate();
    }

    /**
     * to hide the generate AI prompt
     */
    function cancel(): void {
        profile.value = null;
        aiResponse.value = null;
        isOpen.value = false;
        if (isProcessing.value) {
            controller.abort();
        }
    }

    /**
     * The modal can also be closed by the close button,
     * so make sure we also handle that case
     */
    window.Vue.watch(isOpen, (newValue) => {
        if (!newValue) {
            cancel();
        }
    });

    return {
        isProcessing,
        isOpen,
        loadError,
        profile,
        aiResponse,
        prompt,
        cancel,
        generate,
    };
});
