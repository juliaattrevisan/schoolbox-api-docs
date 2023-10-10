<script setup lang="ts">
import { kebabCase } from "lodash";
import { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useUnitPlannerStore } from "./store/unitPlannerStore";

interface Props {
    activities: Activity[];
    unitId: number;
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();

const maxUnexpandedActivities = 6;

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
            <li v-for="activity in displayedActivities" :key="activity.id">
                <div class="list-item">
                    <div class="card small-12 compact wrap-down">
                        <a :href="activity._links.status">
                            <span :class="'icon-' + kebabCase(activity.assessmentType)"></span>
                            <p>{{ activity.title }}</p>
                        </a>
                    </div>
                </div>
            </li>
        </div>
        <div v-if="!isExpanded && props.activities.length > maxUnexpandedActivities" class="show-more-container">
            <div class="component-action">
                <section>
                    <a href="#" class="button" @click.prevent="store.toggleExpanded(unitId)"> Show More </a>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
.card.compact > * {
    height: 2.5rem;
}

.show-more-container {
    position: absolute;
    bottom: 0;
    width: 100%;
}

.activity-list {
    height: 100%;
    padding: 0;
}

.information-list {
    flex-grow: 1;
}
</style>
