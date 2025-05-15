/**
 * To load and display nodes linked specifically to the unit
 */

import { defineStore } from "pinia";
import { getOnlyEnabled } from "@schoolboxCurriculum/js/nodeUtils";
import type { Node } from "@schoolboxCurriculum/types/Node";

export const useCurrentNodesStore = defineStore("csUnitNodesStore", () => {
    const hasLoadError = window.Vue.ref(false);
    const hasLoaded = window.Vue.ref(false); // whether the nodes have been loaded or not
    const nodes = window.Vue.ref<Node[]>([]);
    const hasNodes = window.Vue.ref(false); // no need to display the tree if there are no nodes
    const hasMultipleParents = window.Vue.ref(false); // whether the nodes come from multiple top level curriculum parents

    /**
     * load all the nodes linked to a specific unit
     * @param parentFolderId - the id of the parent folder (course/unit) to load
     */
    async function load(parentFolderId: number): Promise<void> {
        try {
            const response = await fetch(
                `/api/curriculum/usage/folder/${parentFolderId.toString()}?returnFormat=tree&descendants=0`,
                {
                    headers: {
                        "Content-Type": "application/json",
                    },
                },
            );
            if (response.ok) {
                nodes.value = (await response.json()) as Node[];
                nodes.value = getOnlyEnabled(nodes.value);
                if (nodes.value.length > 0) {
                    hasNodes.value = true;
                }
                if (nodes.value.length > 1) {
                    hasMultipleParents.value = true;
                }
            }
        } catch (error) {
            hasLoadError.value = true;
            console.warn("Error loading unit nodes:", error);
        } finally {
            hasLoaded.value = true;
        }
    }

    return {
        hasLoadError,
        hasLoaded,
        nodes,
        hasNodes,
        hasMultipleParents,
        load,
    };
});
