/**
 * To save an entire row of the MDP editor
 */

import { defineStore } from "pinia";
import type { MDPHeader, Header } from "../../js/types/manageDifferentiationProfilesTypes";
import { HeaderType } from "../../js/types/manageDifferentiationProfilesTypes";

export const useMdpSaveStore = defineStore(`mdpSaveStore`, () => {
    const header = window.Vue.ref<Header[]>([]);

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
                isFixed: true,
                isSortable: true,
                key: "student",
                type: HeaderType.USER,
            },
            ...$header.map((h) => ({
                id: h.id,
                key: h.name,
                type:
                    h.name === "adjustment_category" || h.name === "adjustment_level"
                        ? HeaderType.ADJUSTMENT
                        : HeaderType.TEXT,
                title: h.terminology,
                width: 400,
                isFixed: false,
                isSortable: false,
            })),
            {
                id: Math.max(0, ...$header.map((h) => h.id)) + 1,
                key: "updated_at",
                type: HeaderType.DATE,
                title: "Last updated",
                width: 200,
                isFixed: false,
                isSortable: true,
            },
        ];
    }

    return {
        header,
        init,
    };
});
