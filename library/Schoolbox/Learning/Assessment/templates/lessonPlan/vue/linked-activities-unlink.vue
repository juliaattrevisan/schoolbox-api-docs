<script setup lang="ts">
import { kebabCase } from "lodash";
import { useLinkedActivitiesStore } from "./store/linkedActivitiesStore";

const store = useLinkedActivitiesStore();

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
    <h2>Unlink {{ activitiesToRemove.length > 1 ? "activities" : "activity" }}?</h2>
    <p>
        Are you sure you wish to unlink the following
        {{ activitiesToRemove.length > 1 ? "activities" : "activity" }} from this lesson plan?
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
