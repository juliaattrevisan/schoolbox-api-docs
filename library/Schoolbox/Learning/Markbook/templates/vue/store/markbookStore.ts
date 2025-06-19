import { defineStore } from "pinia";
import type { Markbook } from "../types/payload";

export const useMarkbookStore = defineStore("markbookStore", () => {
    const endpoint = window.Vue.ref<string>("");
    const markbook = window.Vue.ref<Markbook | null>(null);

    const showDisplayMarks = window.Vue.ref(false);
    const showDrafts = window.Vue.ref(false);

    /**
     * Fetch markbook data
     */
    async function fetchMarkbook(): Promise<void> {
        if (!endpoint.value) {
            throw new Error("No markbook endpoint provided");
        }

        // todo: add error handling and filters support...
        const response = await fetch(endpoint.value, {
            method: "POST",
        });

        markbook.value = (await response.json()) as Markbook;
    }

    /**
     * Initialise the store
     * @param $endpoint - The endpoint for the markbook data
     */
    async function init($endpoint: string): Promise<void> {
        endpoint.value = $endpoint;
        await fetchMarkbook();
    }

    return {
        init,
        data: markbook,
        showDisplayMarks,
        showDrafts,
    };
});
