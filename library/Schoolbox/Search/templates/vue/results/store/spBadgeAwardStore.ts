import { defineStore } from "pinia";
import type { Result } from "../../../js/types/Result";

type BadgeAward = {
    id: number;
    achievementExpiry: number | null;
    goalExpiry: number | null;
    name: string;
    type: "achievement" | "goal";
    _links: {
        icon: string;
    };
};

export const useSPBadgeAwardStore = defineStore("spBadgeAwardStore", () => {
    const selectedBadge = window.Vue.ref<BadgeAward | null>(null);

    /**
     * Set the selected badge
     * @param badge The badge to set
     * @param awardType The type of award, either "achievement" or "goal"
     */
    function setSelectedBadge(badge: Result, awardType: BadgeAward["type"]) {
        selectedBadge.value = {
            id: badge.id,
            // achievementExpiry: badge.achievementExpiry,
            // goalExpiry: badge.goalExpiry,
            achievementExpiry: null,
            goalExpiry: null,
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
