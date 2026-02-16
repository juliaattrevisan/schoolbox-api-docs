/**
 * To hold the header data for the Customisation Records,
 * to make any display changes to the grid
 */

import { defineStore } from "pinia";
import type { Header } from "@schoolboxVue/components/grid/lazyGrid/types/lazyGridTypes";

export const useHeaderStore = defineStore(`headerStore`, () => {
    const header = window.Vue.ref<Header[]>([]);

    /**
     * store any passed through properties
     * @param $header - the headers for the grid
     */
    function init($header: Header[]): void {
        header.value = [
            ...$header.map((h) => {
                let width = 300;
                if (h.key === "user") {
                    width = 250;
                } else if (h.key === "created_at") {
                    width = 150;
                }
                return {
                    ...h,
                    width,
                    isSticky: h.key === "user",
                };
            }),
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
        init,
        getTitleByKey,
    };
});
