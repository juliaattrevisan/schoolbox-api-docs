<script setup lang="ts">
/**
 * The main container for an activities list
 * Activities can be dragged and dropped to reorder the activities list
 * This does not handle the component-action for creating new activities, which should be created separately
 * If initialising this component with an existing store after, set preventStoreInit to true.
 * Note, only supports 1 instance per activitiesListStoreId where the activities will be draggable,
 * components initialised with preventStoreInit=true are only for display purposes, e.g. in the unit planner if resequencing a unit
 */
import type { Ref } from "vue";
import type { Activity, ActivitiesListRoutes } from "@schoolboxAssessment/Types/assessmentTypes";
import { useActivitiesListStore } from "./store/activitiesListStore";
import ActivitiesListContentItem from "./activities-list-content-item.vue";
import { useActivitiesListDragStore } from "./store/activitiesListDragStore";

interface Props {
    activitiesListStoreId: string; // A unique identifier for this activities list
    activities: Activity[];
    activitiesListRoutes: ActivitiesListRoutes;
    preventStoreInit: boolean; // Whether to skip store initialization, e.g. if we're using an existing store
}

const props = defineProps<Props>();

const activitiesListStore = useActivitiesListStore(props.activitiesListStoreId);
const activitiesListDragStore = useActivitiesListDragStore(props.activitiesListStoreId);

const activitiesContainerEl = window.Vue.ref<HTMLElement | null>(null);

window.Vue.onMounted(() => {
    if (!props.preventStoreInit) {
        activitiesListDragStore().init(activitiesContainerEl as Ref<HTMLElement>);
    }
});
</script>

<template>
    <ul ref="activitiesContainerEl" class="information-list sortable-card ui-sortable">
        <activities-list-content-item
            v-for="activity in activitiesListDragStore().orderedActivities"
            :key="activity.id"
            :activity="activity"
            :activities-list-store-id="activitiesListStoreId"
            :is-drag-placeholder="false"
            :prevent-draggable-init="preventStoreInit"
            @deleted="activitiesListStore().removeActivity(activity.id)"
        />
        <activities-list-content-item
            v-if="activitiesListDragStore().isDragging"
            :key="`${activitiesListDragStore().dragCurrentActivity!.id}-placeholder`"
            :activity="activitiesListDragStore().dragCurrentActivity!"
            :activities-list-store-id="activitiesListStoreId"
            :is-drag-placeholder="true"
            :prevent-draggable-init="true"
        />
    </ul>
</template>

<style scoped></style>
