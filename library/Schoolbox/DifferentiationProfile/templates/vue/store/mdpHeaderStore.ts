/**
 * To hold the header data for the MDP editor
 */

import { defineStore } from "pinia";
import type { MDPHeader, Header, ProfileTextKeys } from "../../js/types/manageDifferentiationProfilesTypes";
import { HeaderType } from "../../js/types/manageDifferentiationProfilesTypes";

export const useHeaderStore = defineStore(`headerStore`, () => {
    const header = window.Vue.ref<Header[]>([]);

    // List of ProfileTextKeys to filter by
    const profileTextKeys: ProfileTextKeys[] = [
        "overview",
        "interests_strengths",
        "needs_challenges",
        "strategies_adjustments",
        "goals",
    ];

    // Computed ref for enabled fields that match ProfileTextKeys
    const enabledFields = window.Vue.computed(() => {
        return header.value.filter((h) => profileTextKeys.includes(h.key as ProfileTextKeys));
    });

    /**
     * store any passed through properties
     * @param $header - the headers for the grid
     */
    function init($header: MDPHeader[]): void {
        header.value = [
            {
                id: 0,
                title: "Student",
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
        enabledFields,
        init,
        getTitleByKey,
    };
});
