/**
 * Delete the clicked activity
 */

import { defineStore } from "pinia";
import { useUnitPlannerStore } from "./unitPlannerStore";

export const useDeleteActivityStore = defineStore("unitPlannerDeleteActivity", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed

    /**
     * to display a prompt to confirm a node removal
     */
    function prompt() {
        isOpen.value = true;
    }

    /**
     * To delete all the marked nodes in parallel
     * @returns { Array } - list of fetches
     */
    function getFetches() {
        const fetchList = [];
        const selectedNodesStore = useUnitPlannerStore();
        //        selectedNodesStore.nodes.forEach((node) => {
        //            fetchList.push(
        //                fetch(node._links.delete, {
        //                    method: "DELETE",
        //                    headers: {
        //                        "Content-Type": "application/json",
        //                    },
        //                }),
        //            );
        //        });
        //        return fetchList;
    }

    /**
     * to hide the confirmation prompt
     */
    function cancel() {
        isOpen.value = false;
    }

    /**
     * Activity removal has been confirmed so ask the backend to delete the activity,
     * and then remove it from the UI if the back end did its part.
     */
    async function deleteActivity() {
        console.info("unitPlannerDeleteActivityStore.ts deleteActivity()");
        try {
            isProcessing.value = true;
            //            const nodeDeletions = await Promise.all(getFetches());
            //            const deleteResponses = nodeDeletions.map((response) => response.ok);
            //            const isAllResponsesOk = deleteResponses.every((response) => response === true);
            //            if (isAllResponsesOk) {
            //                useSelectedNodesStore().deleteNodes();
            //                cancel();
            //                return 'Deleted activity';
            //            }
            //            throw new Error();
        } catch (error) {
            throw new Error();
        } finally {
            isProcessing.value = false;
        }
    }

    return {
        isProcessing,
        isOpen,
        prompt,
        deleteActivity,
        cancel,
    };
});
