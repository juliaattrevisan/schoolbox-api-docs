<script setup lang="ts">
import { kebabCase } from "lodash";
import { useLinkedActivitiesStore } from "./store/linkedActivitiesStore";

const store = useLinkedActivitiesStore();

const activityLocale = window.sbProps.l10n.get("activity");
const lessonPlanLocale = window.sbProps.l10n.get("lessonPlan");

/**
 * Cancel the unlink modal
 */
function cancelUnlink() {
    store.isSingleUnlinkOpen = false;
    if (store.isOpen) {
        store.isPreviousSelected = true;
    }
}

const activitiesToRemove = window.Vue.computed(() => {
    return store.getRemovedActivities();
});
</script>

<template>
    <h2>Unlink {{ activityLocale.translate(activitiesToRemove.length > 1, false) }}?</h2>
    <p>
        Are you sure you wish to unlink the following
        {{ activityLocale.translate(activitiesToRemove.length > 1, false) }}
        from this {{ lessonPlanLocale.singular() }}?
    </p>
    <p v-for="activity in activitiesToRemove" :key="activity.id">
        <span :class="'icon-' + kebabCase(activity.assessmentType)"></span>
        {{ activity.title }}
    </p>

    <ul class="flex-list buttons">
        <li><a class="button submit" @click.prevent="store.saveSelectedActivities(true)">Unlink</a></li>
        <li><a class="button" @click.prevent="cancelUnlink()">Cancel</a></li>
    </ul>
</template>

<style scoped></style>
