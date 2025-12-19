<script setup lang="ts">
/**
 * A component-action that can be used alongside the activities list
 * Currently has the ability to create a new activity via a dropdown of activity types
 */
import locale from "@js/terminology/rosettaStone";
import { useActivitiesListStore } from "./store/activitiesListStore";
import ActivitiesListActionCreateInner from "./activities-list-action-create-inner.vue";

interface Props {
    activitiesListStoreId: string;
}

const props = defineProps<Props>();
const activitiesListStore = useActivitiesListStore(props.activitiesListStoreId);
</script>

<template>
    <div v-if="activitiesListStore().routes.create !== null" class="component-action">
        <section>
            <sbx-pop-out
                :content-id="`dropdown-activities-list-activity-${props.activitiesListStoreId}`"
                :button-text="`Create ${locale.activity.proper()}`"
                move-to="#unitplanner-dropdown-container"
                is-component-action
            ></sbx-pop-out>
            <activities-list-action-create-inner
                :content-id="`dropdown-activities-list-activity-${props.activitiesListStoreId}`"
                :create-route="activitiesListStore().routes.create!"
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
