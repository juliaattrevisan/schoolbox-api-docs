/**
 * To load and display nodes linked specifically to the unit
 */

import { defineStore } from "pinia";
import type { Node } from "@schoolboxCurriculum/types/Node";

export const useUnitNodesStore = defineStore("csUnitNodesStore", () => {
    const isProcessing = window.Vue.ref(false);
    const hasLoadError = window.Vue.ref(false);
    const hasLoaded = window.Vue.ref(false); // whether the nodes have been loaded or not
    const nodes = window.Vue.ref<Node[]>([]);
    const hasNodes = window.Vue.ref(false); // no need to display the tree if there are no nodes

    /**
     * load all the nodes linked to a specific unit
     * @param folderId - the id of the folder (unit) to load
     */
    async function load(folderId: number): Promise<void> {
        try {
            isProcessing.value = true;
            const response = await fetch(`/api/curriculum/usage/folder/${folderId}?returnFormat=tree&descendants=0`, {
                headers: {
                    "Content-Type": "application/json",
                },
            });
            if (response.ok) {
                nodes.value = (await response.json()) as Node[];
                if (nodes.value.length > 0) {
                    hasNodes.value = true;
                }
            } else {
                // toast.timeout({ text: responseText.title, isSuccess: false });
            }
        } catch (error) {
            // toast.timeout({ text: "There was an unexpected error", isSuccess: false });
        } finally {
            isProcessing.value = false;
            hasLoaded.value = true;
        }
    }

    return {
        isProcessing,
        hasLoadError,
        hasLoaded,
        nodes,
        hasNodes,
        load,
    };
});
