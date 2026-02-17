/**
 * To hold the header data for the MDP editor
 */

import { defineStore } from "pinia";
import type { MDPHeader, Header } from "../../js/types/manageDifferentiationProfilesTypes";
import { HeaderType } from "../../js/types/manageDifferentiationProfilesTypes";

export const useHeaderStore = defineStore(`headerStore`, () => {
    const header = window.Vue.ref<Header[]>([]);

    // To return only the text fields that are enabled for AI generation
    const enabledTextFields = window.Vue.computed(() => {
        return header.value.filter((h) => h.type === HeaderType.TEXT);
    });

    // So we don't show the AI button when no text fields are enabled
    const hasEnabledTextFields = window.Vue.computed(() => {
        return enabledTextFields.value.length > 0;
    });

    /**
     * store any passed through properties
     * @param $header - the headers for the grid
     */
    function init($header: MDPHeader[]): void {
        header.value = [
            {
                id: 0,
                title: "Name",
                width: 250,
                isSticky: true,
                isSortable: true,
                key: "student",
                type: HeaderType.USER,
            },
            {
                id: Math.max(0, ...$header.map((h) => h.id)) + 1,
                key: "updated_at",
                type: HeaderType.DATE,
                title: "Last updated",
                width: 150,
                isSticky: false,
                isSortable: true,
            },
            ...$header.map((h) => ({
                id: h.id,
                key: h.name,
                type:
                    h.name === "adjustment_category" || h.name === "adjustment_level"
                        ? HeaderType.ADJUSTMENT
                        : HeaderType.TEXT,
                title: h.terminology === null || h.terminology.trim() === "" ? h.title : h.terminology,
                width: h.name === "adjustment_category" || h.name === "adjustment_level" ? 300 : 500,
                isSticky: false,
                isSortable: false,
            })),
        ];
    }

    /**
     * Return the title for a given key
     * @param key - the key to find
     * @returns the title or null if not found
     */
    function getTitleByKey(key: string): string | null {
        const found = header.value.find((h) => h.key === key);
        return found ? found.title : null;
    }

    return {
        header,
        enabledTextFields,
        hasEnabledTextFields,
        init,
        getTitleByKey,
    };
});
