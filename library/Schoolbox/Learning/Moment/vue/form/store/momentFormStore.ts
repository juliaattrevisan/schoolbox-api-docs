import { defineStore } from "pinia";
import { watchDebounced } from "@vueuse/core";
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { StorageFile } from "@js/resource/resourceUtils";
import type { SelectizeSelectorItem } from "@js/vendors/selectize/selectizeSelectorTypes";
import type { Node } from "@schoolboxCurriculum/types/Node";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";
import { EvidenceStatus, type LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";

export enum MomentFormModalType {
    CONFIRM_PUBLISH,
    CONFIRM_REJECT,
    CONFIRM_DELETE,
}

// TODO: move these elsewhere and do it properly (per each endpoint that exists)
type SubmitFormApiResponse = LearningMoment | LearningMoment[];
type SubmitFormApiPayload = {
    body: string;
    evidence: Pick<StorageFile, "hash">[];
    curriculumNodes: Node["code"][];
    tags: Pick<SelectizeSelectorItem, "id">[];
    rejectionReason?: string; // only for reject
    deleteComments?: boolean; // only for publish
    folders: number[];
};

export const useMomentFormStore = defineStore("momentForm", () => {
    const hasAutosave = window.Vue.ref<boolean>(false);
    const returnUrl = window.Vue.ref<string>("/");
    const createRoutes = window.Vue.ref<null | LearningMoment["_links"]>(null);
    const moment = window.Vue.ref<LearningMoment | null>(null);
    const folders = window.Vue.ref<{ id: number; title: string }[]>([]);
    const selectedFolders = window.Vue.ref<number[]>([]);

    const modalType = window.Vue.ref<MomentFormModalType | null>(null);

    const selectedNodesStore = useSelectedNodesStore();

    const isSaving = window.Vue.ref<boolean>(false);
    const saveError = window.Vue.ref<string | null>(null);

    /**
     * The current payload of the form
     */
    const formPayload = window.Vue.computed(() => {
        if (!moment.value) {
            return null;
        }

        return {
            body: moment.value.body ?? "",
            // TODO evidence: moment.value.evidence.map((e) => { file: "store:2e378430962ef7470659bb0462c7758915fdc820:Screencast.webm" })
            evidence: [],
            curriculumNodes: selectedNodesStore.nodesIds.map(String),
            tags: (moment.value.tags ?? []).map((tag) => ({ id: tag.id })),
            folders: selectedFolders.value,
        } satisfies SubmitFormApiPayload;
    });

    /**
     * Submit the form and redirect to the return URL provided
     * This is either a POST or PUT depending on if we're creating or editing
     * @param isAutosave whether this is an autosave or a manual save
     * @param action the action to perform
     * @param rejectionReason the reason for rejection, only relevant for reject
     * @param deleteComments whether to delete comments, only relevant for publish
     */
    async function submitForm(
        isAutosave: boolean,
        action: "draft" | "publish" | "reject" | "submit",
        rejectionReason?: string,
        deleteComments?: boolean,
    ): Promise<void> {
        const payload: SubmitFormApiPayload | null = formPayload.value ? { ...formPayload.value } : null;
        if (!moment.value || payload === null) return;

        // TODO: validation of body/evidence, need at least one of them I guess

        if (action === "publish") {
            payload.deleteComments = deleteComments;
        }

        if (action === "reject") {
            payload.rejectionReason = rejectionReason;
        }

        const apiRequestInfo = createRoutes.value
            ? ({ method: "POST", url: createRoutes.value[action] } as const)
            : ({ method: "PUT", url: moment.value._links[action] } as const);

        if (!apiRequestInfo.url) {
            throw new Error(`No ${action} route available`);
        }

        const toast = window.sbProps.toastActivate("Saving...");
        isSaving.value = true;
        saveError.value = null;

        const response = await apiRequest<SubmitFormApiResponse, SubmitFormApiPayload>({
            method: apiRequestInfo.method,
            url: apiRequestInfo.url,
            body: payload,
        });

        isSaving.value = false;

        if (!response.success) {
            saveError.value = response.message;
            toast.timeout({ text: "Save failed!", isSuccess: false });
            return;
        }

        toast.timeout({ text: "Saved", isSuccess: true });

        /**
         * Initial create response is an array of moments
         * modify form is a single moment (no array)
         * so check if the response is an array or not
         * and update the form accordingly
         */
        const newMoments = Array.isArray(response.data) ? response.data : [response.data];

        /**
         * Send the user back to where they came from if either:
         * - This is not an autosave or,
         * - This is a bulk create (multiple moments were returned)
         */
        if (!isAutosave || newMoments.length > 1) {
            window.location.href = returnUrl.value;
            return;
        }

        /**
         * otherwise, update the moment in the store to reflect any changes
         * for now this just means updating the _links, as that's all that *should* change...
         */
        moment.value._links = newMoments[0]._links;

        // If we were creating, we need to clear the create routes as we're now editing
        if (createRoutes.value) {
            createRoutes.value = null;
        }

        // Update the window history
        if (moment.value._links.modifyForm) {
            window.history.pushState({}, "", moment.value._links.modifyForm);
        }
    }

    /**
     * Toggle selection of a folder
     * @param folderId The ID of the folder to toggle
     */
    function toggleFolderSelection(folderId: number): void {
        const index = selectedFolders.value.indexOf(folderId);
        if (index === -1) {
            selectedFolders.value.push(folderId);
        } else {
            selectedFolders.value.splice(index, 1);
        }
    }

    /**
     * Initialise the store
     * @param $hasAutosave If autosave is available
     * @param $returnUrl The URL to return to after saving
     * @param $createRoutes The routes to use when creating a new moment, null if editing an existing one
     * @param $moment The learning moment relevant to this form
     * @param $folders The list of available folders to select from
     * @param $selectedFolders The list of currently selected folder IDs
     */
    function init(
        $hasAutosave: boolean,
        $returnUrl: string,
        $createRoutes: null | LearningMoment["_links"],
        $moment: LearningMoment,
        $folders: { id: number; title: string }[],
        $selectedFolders: number[],
    ): void {
        hasAutosave.value = $hasAutosave;
        returnUrl.value = $returnUrl;
        createRoutes.value = $createRoutes;
        moment.value = $moment;
        folders.value = $folders;
        selectedFolders.value = $selectedFolders;

        /**
         * When autosave is possible, keep an eye on the form payload
         * and save when there are changes (debounced 3s)
         */
        if (hasAutosave.value) {
            watchDebounced(
                () => formPayload.value,
                async (newVal, oldVal) => {
                    if (!moment.value) return;
                    if (isSaving.value) return;
                    if (JSON.stringify(newVal) === JSON.stringify(oldVal)) return;

                    // can't autosave if the user can't access the draft route
                    if (!moment.value._links.draft && !createRoutes.value?.draft) return;

                    // no autosaving of published moments
                    if (moment.value.currentState === EvidenceStatus.PUBLISHED) return;

                    await submitForm(true, "draft");
                },
                { debounce: 3000, deep: true },
            );
        }
    }

    return {
        init,
        hasAutosave,
        createRoutes,
        moment,
        folders,
        selectedFolders,
        modalType,
        isSaving,
        toggleFolderSelection,
    };
});
