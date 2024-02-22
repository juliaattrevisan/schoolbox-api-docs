<script setup lang="ts">
import { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import ActivitiesListItem from "./activities-list-item.vue";

interface Props {
    activities: Activity[];
    unitId: number;
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();

const maxUnexpandedActivities = 7;

const isExpanded = window.Vue.computed(() => {
    if (props.activities.length <= maxUnexpandedActivities) return true;
    if (store.expandedUnits.includes(props.unitId)) return true;
    return false;
});

const displayedActivities = window.Vue.computed((): Activity[] => {
    if (isExpanded.value) {
        return props.activities;
    }
    return props.activities.slice(0, maxUnexpandedActivities);
});
</script>

<template>
    <div class="activity-list">
        <div class="information-list">
            <activities-list-item v-for="activity in displayedActivities" :key="activity.id" :activity="activity" />
        </div>
        <div v-if="!isExpanded && props.activities.length > maxUnexpandedActivities" class="show-more-container">
            <button @click.prevent="store.toggleExpanded(unitId)">Show More</button>
        </div>
    </div>
</template>

<style scoped>
.show-more-container {
    bottom: 0;
    position: absolute;
    width: 100%;
    background: linear-gradient(0deg, #fff 0%, #fff0 100%);
    pointer-events: none;
}
.show-more-container button {
    margin: 5px;
    float: right;
    pointer-events: all;
}

.activity-list {
    height: 100%;
    min-width: 400px;
    padding: 0 0 5px 0;
}

.information-list {
    flex-grow: 1;
}
</style>
