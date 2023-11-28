/**
 * Handles badge related events for the search page,
 * including opening the badge modal and awarding them as goals and achievements
 */

import { defineStore } from "pinia";
import type { BadgeUsageObject } from "@schoolboxCurriculum/types/Node";
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
    const isBadgeModalOpen = window.Vue.ref(false);
    const currentBadge = window.Vue.ref<BadgeUsageObject | null>(null); // the badge to display in the modal when a badge usage is selected
    const selectedBadge = window.Vue.ref<BadgeAward | null>(null);

    /**
     * Opens a modal window for viewing this badge's full details.
     * @param { BadgeUsageObject } badge - the badge to display
     */
    function openDetailModal(badge: BadgeUsageObject) {
        currentBadge.value = badge;
        isBadgeModalOpen.value = true;
    }

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
        isBadgeModalOpen,
        currentBadge,
        openDetailModal,
        selectedBadge,
        setSelectedBadge,
        unsetSelectedBadge,
    };
});
