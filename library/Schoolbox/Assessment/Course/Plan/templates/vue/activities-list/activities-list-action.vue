<script setup lang="ts">
/**
 * A component-action that can be used alongside the activities list
 * Currently has the ability to create a new activity via a dropdown of activity types
 */
import { useActivitiesListGlobalStore } from "./store/activitiesListGlobalStore";
import { useActivitiesListStore } from "./store/activitiesListStore";
import ActivitiesListActionCreateInner from "./activities-list-action-create-inner.vue";

interface Props {
    activitiesListStoreId: string;
}

const props = defineProps<Props>();
const activitiesListGlobalStore = useActivitiesListGlobalStore();
const activitiesListStore = useActivitiesListStore(props.activitiesListStoreId);

const activityLocale = window.sbProps.l10n.get("activity");
</script>

<template>
    <div v-if="activitiesListStore().routes.create !== null" class="component-action">
        <section>
            <sbx-pop-out
                :content-id="`dropdown-activities-list-activity-${props.activitiesListStoreId}`"
                :button-text="`Create ${activityLocale.proper()}`"
                move-to="#unitplanner-dropdown-container"
                is-component-action
            ></sbx-pop-out>
            <activities-list-action-create-inner
                :content-id="`dropdown-activities-list-activity-${props.activitiesListStoreId}`"
                :create-route="activitiesListStore().routes.create!"
                :lti-assessments-enabled="activitiesListGlobalStore.ltiAssessmentsEnabled"
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
