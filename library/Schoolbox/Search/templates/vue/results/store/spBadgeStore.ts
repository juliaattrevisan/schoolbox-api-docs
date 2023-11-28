/**
 * Handles badge related events for the search page,
 * including opening the badge modal and awarding them as goals and achievements
 */

import { defineStore } from "pinia";
import type { Result } from "../../../js/types/Result";

type BadgeAward = {
    id: number;
    expiry: number | null;
    name: string;
    type: "achievement" | "goal";
    _links: {
        icon: string;
    };
};

export const useBadgeStore = defineStore("spBadgeStore", () => {
    const selectedBadge = window.Vue.ref<BadgeAward | null>(null);

    /**
     * Set the selected badge
     * @param {Result} badge The badge to set
     * @param {BadgeAward["type"]} awardType The type of award, either "achievement" or "goal"
     * @param {BadgeAward["expiry"]} awardExpiry The expiry of the award
     */
    function setSelectedBadge(badge: Result, awardType: BadgeAward["type"], awardExpiry: BadgeAward["expiry"]) {
        selectedBadge.value = {
            id: badge.id,
            expiry: awardExpiry,
            name: badge.title,
            type: awardType,
            _links: {
                icon: badge.image || "",
            },
        };
    }

    /**
     * Unset the selected badge
     */
    function unsetSelectedBadge() {
        selectedBadge.value = null;
    }

    return {
        selectedBadge,
        setSelectedBadge,
        unsetSelectedBadge,
    };
});
