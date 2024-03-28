<script setup lang="ts">
import type { Ref } from "vue";
import type { Activity, ActivityTypeLocale, ActivitiesListRoutes } from "@schoolboxAssessment/Types/assessmentTypes";
import Toast from "@schoolboxVue/components/toast.vue";
import { useActivitiesListGlobalStore } from "./store/activitiesListGlobalStore";
import { useActivitiesListStore } from "./store/activitiesListStore";
import ActivitiesListItem from "./activities-list-item.vue";

interface Props {
    activitiesListStoreId: string; // A unique identifier for this activities list
    activities: Activity[];
    activityTypeLocale: ActivityTypeLocale;
    activitiesListRoutes: ActivitiesListRoutes;
    ltiAssessmentsEnabled: boolean;
    toastEl: typeof Toast;
}

const props = defineProps<Props>();
const activitiesListGlobalStore = useActivitiesListGlobalStore();

// To allow for multiple activity lists on the same page, the store is created with a unique id
const activitiesListStore = useActivitiesListStore(props.activitiesListStoreId);
const activitiesContainerEl = window.Vue.ref<HTMLElement | null>(null);

window.Vue.onMounted(() => {
    activitiesListGlobalStore.init(
        props.activityTypeLocale,
        props.activitiesListRoutes,
        props.ltiAssessmentsEnabled,
        props.toastEl,
    );

    activitiesListStore().init(
        props.unit.activities._links.reorder,
        activitiesContainerEl as Ref<HTMLElement>,
        props.unit.activities.items,
        props.isSortablePlaceholder,
    );
});
</script>

<template>
    <ul ref="activitiesContainerEl" class="information-list sortable-card ui-sortable">
        <activities-list-item
            v-for="activity in activitiesListStore().orderedActivities"
            :key="activity.id"
            :activity="activity"
            :activities-list-store-id="activitiesListStoreId"
        />
    </ul>
</template>

<style scoped></style>
