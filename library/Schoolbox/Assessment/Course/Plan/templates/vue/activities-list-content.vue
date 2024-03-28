<script setup lang="ts">
import type { Ref } from "vue";
import type { Activity, ActivityTypeLocale, ActivitiesListRoutes } from "@schoolboxAssessment/Types/assessmentTypes";
import Toast from "@schoolboxVue/components/toast.vue";
import { useActivitiesListStore } from "./store/activitiesListStore";
import ActivitiesListItem from "./activities-list-item.vue";
import { useActivitiesListDragStore } from "./store/activitiesListDragStore";

interface Props {
    activitiesListStoreId: string; // A unique identifier for this activities list
    activities: Activity[];
    activityTypeLocale: ActivityTypeLocale;
    activitiesListRoutes: ActivitiesListRoutes;
    ltiAssessmentsEnabled: boolean;
    toastEl: typeof Toast;
}

const props = defineProps<Props>();
const emit = defineEmits(["dragStart", "dragStop"]);

const activitiesListStore = useActivitiesListStore(props.activitiesListStoreId);
const activitiesListDragStore = useActivitiesListDragStore(props.activitiesListStoreId);

const activitiesContainerEl = window.Vue.ref<HTMLElement | null>(null);

window.Vue.onMounted(() => {
    activitiesListStore().init(props.activities, props.activitiesListRoutes);
    activitiesListDragStore().init(
        activitiesContainerEl as Ref<HTMLElement>,
        () => emit("dragStart"),
        () => emit("dragStop"),
    );
});
</script>

<template>
    <ul ref="activitiesContainerEl" class="information-list sortable-card ui-sortable">
        <activities-list-item
            v-for="activity in activitiesListDragStore().orderedActivities"
            :key="activity.id"
            :activity="activity"
            :activities-list-store-id="activitiesListStoreId"
        />
    </ul>
</template>

<style scoped></style>
