<script setup lang="ts">
import { kebabCase } from "lodash";
import { getGradient } from "@js/util/cssUtils";
import { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useLinkedActivitiesStore } from "./store/linkedActivitiesStore";

interface Props {
    activity: Activity;
    isInstructor: boolean;
    returnUrl: string;
}

const props = defineProps<Props>();

const store = useLinkedActivitiesStore();

/**
 * Get the name of the unit that the activity belongs to
 * Will also include the project name if the activity is part of a project
 * @param { Activity } activity The activity to get the meta for
 * @returns { string } Unit name with project name if applicable
 */
function getActivityMeta(activity: Activity): string {
    return activity.unit + (activity.projectName ? ` | ${activity.projectName}` : "");
}

/**
 * Handle the unlink button click event
 */
function handleUnlinkClick() {
    store.clearChanges();
    store.removeActivity(props.activity);
    store.isSingleUnlinkOpen = true;
}

/**
 * Get the urgency class for the activity
 * @returns {string} The urgency class
 */
function getUrgency() {
    if (store.gradeGradientEnable) {
        return getGradient(props.activity.completionCount, props.activity.studentCount);
    }
    return "gradient-none";
}
</script>

<template>
    <div class="card small-12">
        <h3>
            <span :class="'icon-' + kebabCase(activity.assessmentType)"></span>
            <a
                v-if="activity._links !== null"
                class="title"
                :href="activity._links.status + '?returnUrl=' + returnUrl"
                >{{ activity.title }}</a
            >
            <span v-else class="title">{{ activity.title }}</span>
        </h3>
        <p class="meta">
            {{ getActivityMeta(activity) }}
            <template v-if="props.isInstructor">
                |
                <span :class="getUrgency()"
                    >{{ props.activity.completionCount }}/{{ props.activity.studentCount }} students completed</span
                >
            </template>
        </p>
        <p v-if="activity.dueAtFormatted" class="meta">Due {{ activity.dueAtFormatted }}</p>
    </div>
    <nav v-if="isInstructor">
        <a class="icon-close no-text" title="Unlink Assessment" @click.prevent="handleUnlinkClick()"></a>
    </nav>
</template>

<style scoped></style>
