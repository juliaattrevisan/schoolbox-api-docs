/**
 * To manage the selection of generating AI content for a profile
 */

import { defineStore } from "pinia";
import { apiRequest } from "@js/util/sbx/apiUtils";
import locale from "@js/terminology/rosettaStone";
import type { Profile } from "../../js/types/manageDifferentiationProfilesTypes";
import { useSaveStore } from "./mdpSaveStore";

export const useGenAiStore = defineStore(`genAiStore`, () => {
    const saveStore = useSaveStore();
    const isGenerating = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create
    const profileToGenerate = window.Vue.ref<Profile | null>(null);

    /**
     * to display a prompt to generate AI content for a profile
     * @param profile - the profile to generate content for
     */
    function prompt(profile: Profile): void {
        profileToGenerate.value = profile;
        isOpen.value = true;
    }

    /**
     * to hide the generate AI prompt
     */
    function cancel(): void {
        profileToGenerate.value = null;
        isOpen.value = false;
    }

    /**
     * Save the given student row
     * @param profile - the profile to save
     */
    async function generate(profile: Profile): Promise<void> {
        // if (isGenerating.value) {
        //     return;
        // }
        // if (profile._routes.edit === undefined) {
        //     loadError.value = "Edit route is undefined";
        //     return;
        // }
        // const toast = window.sbProps.toastActivate("Generating...");
        // try {
        //     isGenerating.value = true;
        //     loadError.value = null;
        //     const response = await apiRequest({
        //         url: profile._routes.edit,
        //         method: "POST",
        //         body: profile,
        //     });
        //     if (!response.success) {
        //         throw new Error(response.message || "Failed to generate");
        //     }
        //     toast.timeout({ text: `${locale.differentiationProfile.proper()} successfully updated` });
        //     // the date in the format of for example 24/05/2024
        //     profile.updated_at = window.dayjs(new Date()).format("DD/MM/YYYY");
        // } catch (error) {
        //     const errorMessage = error instanceof Error ? error.message : "There was an unexpected error";
        //     loadError.value = errorMessage;
        //     toast.timeout({ text: errorMessage, isSuccess: false });
        // } finally {
        //     isGenerating.value = false;
        // }
        // throw in a quick data test for now

        profile.overview =
            "<p>This student is a highly motivated learner with a keen interest in science and technology. They excel in collaborative projects and demonstrate strong problem-solving skills.</p>";
        profile.interests_strengths =
            "<p>Enjoys reading science fiction and has a strong aptitude for mathematics.</p>";
        profile.needs_challenges =
            "<p>Requires additional support in developing writing skills and time management.</p>";
        profile.strategies_adjustments =
            "<p>Incorporate visual aids and hands-on activities to enhance understanding. Provide clear instructions and checklists for assignments.</p>";
        profile.goals =
            "<p>Improve writing skills by attending weekly workshops. Enhance time management through the use of planners and regular check-ins.</p>";
        await saveStore.save(profile);
    }

    return {
        isGenerating,
        isOpen,
        loadError,
        profileToGenerate,
        prompt,
        cancel,
        generate,
    };
});
