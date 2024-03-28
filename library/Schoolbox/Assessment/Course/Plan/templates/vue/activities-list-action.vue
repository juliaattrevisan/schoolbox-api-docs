<script setup lang="ts">
import { useActivitiesListGlobalStore } from "./store/activitiesListGlobalStore";
import { useActivitiesListStore } from "./store/activitiesListStore";
import ActivitiesListActionCreateInner from "./activities-list-action-create-inner.vue";

interface Props {
    activitiesListStoreId: string;
}

const props = defineProps<Props>();
const activitiesListGlobalStore = useActivitiesListGlobalStore();
const activitiesListStore = useActivitiesListStore(props.activitiesListStoreId);
</script>

<template>
    <div v-if="activitiesListStore().routes.create !== null" class="component-action">
        <section>
            <sbx-pop-out
                :content-id="`dropdown-activities-list-activity-${props.activitiesListStoreId}`"
                button-text="Create Activity"
                move-to="#unitplanner-dropdown-container"
                is-component-action
            ></sbx-pop-out>
            <activities-list-action-create-inner
                :content-id="`dropdown-activities-list-activity-${props.activitiesListStoreId}`"
                :create-route="activitiesListStore().routes.create!"
                :lti-assessments-enabled="activitiesListGlobalStore.ltiAssessmentsEnabled"
                :activity-type-locale="activitiesListGlobalStore.locale"
            ></activities-list-action-create-inner>
        </section>
    </div>
</template>

<style scoped>
.component-action section {
    border-bottom: 0;
    pointer-events: all;
}
</style>
