/**
 * Handles badge related events for the search page,
 * including opening the badge modal and awarding them as goals and achievements
 */

import { defineStore } from "pinia";
import type { Badge } from "@schoolboxBadge/types/Badge";
import type { BadgeAward } from "@schoolboxBadge/types/BadgeAward";

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
     * Set the badge to award
     * @param {BadgeAward} $badgeAward - the badge to award
     */
    function setAwardBadge($badgeAward: BadgeAward) {
        awardBadge.value = $badgeAward;
    }

    /**
     * Unset the selected badge
     */
    function unsetAwardBadge() {
        awardBadge.value = null;
    }

    return {
        isDetailModalOpen,
        detailBadge,
        openDetailModal,
        awardBadge,
        setAwardBadge,
        unsetAwardBadge,
    };
});
