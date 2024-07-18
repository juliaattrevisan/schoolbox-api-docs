<script setup lang="ts">
/**
 * To provide access to further actions on a result
 */

import type { Badge } from "@schoolboxBadge/templates/types/Badge";
import type { BadgeAward } from "@schoolboxBadge/templates/types/BadgeAward";
import { useBadgeStore } from "./store/spBadgeStore";
import { Result } from "../../js/types/Result";

interface Props {
    result: Result; // the result to display
}
const props = defineProps<Props>();
const badgeStore = useBadgeStore();

/**
 * Get the badge to award, adding the type and expiry to it
 * @param {BadgeAward["type"]} type - The type of badge to award
 */
async function awardBadge(type: BadgeAward["type"]) {
    const getBadge = await fetch(`learning/badge/${props.result.id}`);
    const badge = (await getBadge.json()) as Badge;
    const badgeAward: BadgeAward = {
        ...badge,
        type,
        expiry: type === "goal" ? badge.goalExpiry : badge.achievementExpiry,
    };
    badgeStore.setAwardBadge(badgeAward);
}
</script>

<template>
    <sbx-pop-out :content-id="`dropdown-${props.result.id}`"></sbx-pop-out>
    <ul :id="`dropdown-${props.result.id}`" class="f-dropdown" data-dropdown-content>
        <li v-for="link in props.result._links" :key="link.id">
            <a v-if="link.action === undefined" :href="link.url" :class="link.icon">{{ link.title }}</a>
            <template v-else-if="link.action === 'awardAchievementModal'">
                <a :class="link.icon" @click.prevent="awardBadge('achievement')">{{ link.title }}</a>
            </template>
            <template v-else-if="link.action === 'setGoalModal'">
                <a :class="link.icon" @click.prevent="awardBadge('goal')">{{ link.title }}</a>
            </template>
        </li>
    </ul>
</template>

<style scoped></style>
