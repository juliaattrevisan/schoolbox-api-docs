/**
 * To manage the selection of generating AI content for a profile
 */

import { defineStore } from "pinia";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { isAbortError } from "@js/util/errorUtils";
import locale from "@js/terminology/rosettaStone";
import type { Profile } from "../../js/types/manageDifferentiationProfilesTypes";
import { useSaveStore } from "./mdpSaveStore";

export const useGenAiStore = defineStore(`genAiStore`, () => {
    const saveStore = useSaveStore();
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create
    const profile = window.Vue.ref<Profile | null>(null); // the profile to generate AI content for
    let controller: AbortController; // to allow aborting the previous search if a new one is started

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
            controller = new AbortController();
            const response = await apiRequest({
                url: "/openai/differentiation-profile",
                method: "POST",
                signal: controller.signal,
                body: { studentId: profile.value.student.id },
            });
            if (!response.success) {
                throw new Error(response.message || "Failed to generate");
            }
            // the date in the format of for example 24/05/2024
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
        // throw in a quick data test for now

        // profile.overview =
        //     "<p>This student is a highly motivated learner with a keen interest in science and technology. They excel in collaborative projects and demonstrate strong problem-solving skills.</p>";
        // profile.interests_strengths =
        //     "<p>Enjoys reading science fiction and has a strong aptitude for mathematics.</p>";
        // profile.needs_challenges =
        //     "<p>Requires additional support in developing writing skills and time management.</p>";
        // profile.strategies_adjustments =
        //     "<p>Incorporate visual aids and hands-on activities to enhance understanding. Provide clear instructions and checklists for assignments.</p>";
        // profile.goals =
        //     "<p>Improve writing skills by attending weekly workshops. Enhance time management through the use of planners and regular check-ins.</p>";
        // await saveStore.save(profile);
    }

    /**
     * to display a prompt to generate AI content for a profile
     * @param $profile - the profile to generate content for
     */
    function prompt($profile: Profile): void {
        profile.value = $profile;
        isOpen.value = true;
        generate();
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

    /**
     * to hide the generate AI prompt
     */
    function cancel(): void {
        profile.value = null;
        isOpen.value = false;
        if (isProcessing.value) {
            controller.abort();
        }
    }

    return {
        isProcessing,
        isOpen,
        loadError,
        profile,
        prompt,
        cancel,
        generate,
    };
});
