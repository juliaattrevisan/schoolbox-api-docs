<script setup lang="ts">
import type { Ref } from "vue";
import type { Activity, ActivityTypeLocale, ActivitiesListRoutes } from "@schoolboxAssessment/Types/assessmentTypes";
import Toast from "@schoolboxVue/components/toast.vue";
import { useActivitiesListStore } from "./store/activitiesListStore";
import ActivitiesListContentItem from "./activities-list-content-item.vue";
import { useActivitiesListDragStore } from "./store/activitiesListDragStore";

interface Props {
    activitiesListStoreId: string; // A unique identifier for this activities list
    activities: Activity[];
    activityTypeLocale: ActivityTypeLocale;
    activitiesListRoutes: ActivitiesListRoutes;
    ltiAssessmentsEnabled: boolean;
    toastEl: typeof Toast;
    preventStoreInit: boolean; // Whether to skip store initialization, e.g. if we're using an existing store
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: "dragStart"): void;
    (e: "dragStop"): void;
}>();

const activitiesListStore = useActivitiesListStore(props.activitiesListStoreId);
const activitiesListDragStore = useActivitiesListDragStore(props.activitiesListStoreId);

const activitiesContainerEl = window.Vue.ref<HTMLElement | null>(null);

window.Vue.onMounted(() => {
    if (!props.preventStoreInit) {
        activitiesListStore().init(props.activities, props.activitiesListRoutes);
        activitiesListDragStore().init(
            activitiesContainerEl as Ref<HTMLElement>,
            () => emit("dragStart"),
            () => emit("dragStop"),
        );
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
            @deleted="activitiesListStore().removeActivity($event)"
        />
        <aactivities-list-content-item
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
