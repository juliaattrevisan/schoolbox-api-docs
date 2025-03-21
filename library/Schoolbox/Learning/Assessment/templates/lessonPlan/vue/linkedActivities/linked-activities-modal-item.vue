<script setup lang="ts">
import { kebabCase } from "lodash";
import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useLinkedActivitiesStore } from "./store/linkedActivitiesStore";

interface Props {
    activity: Activity;
    sourceId: number;
    projectActivity?: Activity;
}

const props = defineProps<Props>();

const store = useLinkedActivitiesStore();

const activityIsSelected = window.Vue.computed(() => {
    return store.selectedActivities.some((assessmentItem) => assessmentItem.id === props.activity.id);
});

/**
 * Toggle the selected state of an activity
 */
function toggleActivitySelection(): void {
    const linked = activityIsSelected.value;
    if (linked) {
        store.removeActivity(props.activity);
    } else {
        store.addActivity(props.activity);
    }
}
</script>

<template>
    <div>
        <div class="selector">
            <input
                :id="'chk-assessment-' + props.activity.id"
                type="checkbox"
                :checked="activityIsSelected"
                :disabled="props.activity.id === props.sourceId"
                @change="toggleActivitySelection()"
            />
            <label :for="'chk-assessment-' + props.activity.id" class="checkbox-label"></label>
        </div>
        <div class="small-12 compact card wrap-down">
            <span :class="'icon-' + kebabCase(props.activity.assessmentType)"></span>
            <p>
                <template v-if="props.projectActivity"> {{ props.projectActivity.title }} | </template>
                {{ props.activity.title }}
            </p>
            <p v-if="props.activity.dueAt" class="meta" :title="props.activity.dueAt.long">
                <span class="unit-task-due-date">
                    Due
                    <time :datetime="props.activity.dueAt.atom || undefined">{{ props.activity.dueAt.relative }}</time>
                </span>
            </p>
        </div>
    </div>
</template>

<style scoped></style>
