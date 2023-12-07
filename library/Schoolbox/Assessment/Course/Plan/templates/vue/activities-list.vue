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
            <li v-for="activity in displayedActivities" :key="activity.id" class="actions-small-1">
                <div class="list-item">
                    <div class="card small-12 compact wrap-down">
                      <template v-if="activity.assessmentType === 'project'">
                        <a target="_blank" :href="activity._links.status">
                            <span :class="'icon-' + kebabCase(activity.assessmentType)"></span>
                            <p>{{ activity.title }}</p>
                        </a>
                      </template>
                      <template v-else>
                        <a target="_blank" :href="activity._links.summary">
                          <span :class="'icon-' + kebabCase(activity.assessmentType)"></span>
                          <p>{{ activity.title }}</p>
                        </a>
                      </template>
                    </div>
                </div>
                <nav>
                    <sbx-pop-out :content-id="'dropdown-unitplanner-activity-' + activity.id"></sbx-pop-out>
                </nav>
                <ul :id="'dropdown-unitplanner-activity-' + activity.id" class="f-dropdown" data-dropdown-content>
                    <li>
                        <a target="_blank" :href="activity._links.modify" class="icon-modify">Modify</a>
                    </li>
                </ul>
            </li>
        </div>
        <div v-if="!isExpanded && props.activities.length > maxUnexpandedActivities" class="show-more-container">
            <button @click.prevent="store.toggleExpanded(unitId)">Show More</button>
        </div>
    </div>
</template>

<style scoped>
.card.compact {
    padding-right: 0;
}

.card.compact > * {
    height: 2.5rem;
}

.card.compact a p {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    min-width: 0;
}

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
    width: 400px;
    padding: 0 0 5px 0;
}

.information-list {
    flex-grow: 1;
}
</style>
