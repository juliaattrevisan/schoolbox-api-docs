/**
 *
 */

import { defineStore } from "pinia";
import type { Card } from "../types/Card";

export const useCardsStore = defineStore("hmCards", () => {
    const cards = window.Vue.ref<Card[]>([]);

    return { cards };
});
