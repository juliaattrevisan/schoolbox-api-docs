/**
 * Store specific to the /student folder
 * Currently only used to pass through the editor templates
 */

import { defineStore } from "pinia";
import type { Template } from "@js/editor/types/editorTypes";

export const useStudentItemStore = defineStore("studentItemStore", () => {
    const editorTemplates = window.Vue.ref<Template[]>([]); // store the editor templates passed through from the controller

    /**
     * store any passed through properties
     * @param $editorTemplates - the editor templates to use for feedback
     */
    function init($editorTemplates: Template[]): void {
        editorTemplates.value = $editorTemplates;
    }

    return {
        init,
        editorTemplates,
    };
});
