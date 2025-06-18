import { defineStore } from "pinia";
import type { Markbook } from "../types/payload";

export const useMarkbookStore = defineStore("markbookStore", () => {
    const endpoint = window.Vue.ref<string>("");
    const markbook = window.Vue.ref<Markbook | null>(null);

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
    };
});
