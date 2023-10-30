<script setup lang="ts">
/**
 * To provide access to further actions on a result
 */

import { useSPBadgeAwardStore } from "./store/spBadgeAwardStore";
import { Result } from "../../js/types/Result";

interface Props {
    result: Result; // the result to display
}
const props = defineProps<Props>();
const badgeAwardStore = useSPBadgeAwardStore();

/**
 * To open the award achievement modal
 */
function onAwardAchievement() {
    badgeAwardStore.setSelectedBadge(props.result, "achievement");
}

/**
 * To open the set goal modal
 */
function onSetGoal() {
    badgeAwardStore.setSelectedBadge(props.result, "goal");
}
</script>

<template>
    <sbx-pop-out :content-id="`dropdown-${props.result.id}`"></sbx-pop-out>
    <ul :id="`dropdown-${props.result.id}`" class="f-dropdown" data-dropdown-content>
        <li v-for="link in props.result._links" :key="link.id">
            <a v-if="link.action === undefined" :href="link.url" :class="link.icon">{{ link.title }}</a>
            <template v-else-if="link.action === 'awardAchievementModal'">
                <a :class="link.icon" @click.prevent="onAwardAchievement">{{ link.title }}</a>
            </template>
            <template v-else-if="link.action === 'setGoalModal'">
                <a :class="link.icon" @click.prevent="onSetGoal">{{ link.title }}</a>
            </template>
        </li>
    </ul>
</template>

<style scoped></style>
