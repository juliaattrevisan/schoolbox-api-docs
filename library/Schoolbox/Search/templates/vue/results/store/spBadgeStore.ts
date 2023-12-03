/**
 * Handles badge related events for the search page,
 * including opening the badge modal and awarding them as goals and achievements
 */

import { defineStore } from "pinia";
import type { Badge } from "@schoolboxBadge/types/Badge";
import type { BadgeAward } from "@schoolboxBadge/types/BadgeAward";
import type { Result } from "../../../js/types/Result";

export const useBadgeStore = defineStore("spBadgeStore", () => {
    const isDetailModalOpen = window.Vue.ref(false); // to display a badges details
    const detailBadge = window.Vue.ref<Badge | null>(null); // the badge to display details for
    const awardBadge = window.Vue.ref<BadgeAward | null>(null); // the badge to award

    /**
     * Opens a modal window for viewing this badge's full details.
     * @param { badge } badge - the badge to display
     */
    function openDetailModal(badge: Badge) {
        detailBadge.value = badge;
        isDetailModalOpen.value = true;
    }

    /**
     * Set the selected badge
     * @param {Result} badge The badge to set
     * @param {BadgeAward["type"]} awardType The type of award, either "achievement" or "goal"
     * @param {BadgeAward["expiry"]} awardExpiry The expiry of the award
     */
    function setSelectedBadge(badge: Result, awardType: BadgeAward["type"], awardExpiry: BadgeAward["expiry"]) {
        awardBadge.value = {
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
        awardBadge.value = null;
    }

    return {
        isDetailModalOpen,
        detailBadge,
        openDetailModal,
        awardBadge,
        setSelectedBadge,
        unsetSelectedBadge,
    };
});
