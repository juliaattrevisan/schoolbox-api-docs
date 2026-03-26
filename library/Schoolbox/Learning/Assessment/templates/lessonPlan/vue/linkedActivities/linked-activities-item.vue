<script setup lang="ts">
import { kebabCase } from "es-toolkit";
import { getGradient } from "@js/util/cssUtils";
import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import ProgressTick from "@schoolboxLibrary/Learning/Assessment/templates/fragments/vue/progress-tick.vue";
import { useLinkedActivitiesStore } from "./store/linkedActivitiesStore";

interface Props {
    activity: Activity;
    canModify: boolean;
    returnUrl: string;
}

const props = defineProps<Props>();

const store = useLinkedActivitiesStore();

/**
 * Get the name of the unit that the activity belongs to
 * Will also include the project name if the activity is part of a project
 * @param activity The activity to get the meta for
 * @returns Unit name with project name if applicable
 */
function getActivityMeta(activity: Activity): string {
    return activity.unit + (activity.projectName ? ` | ${activity.projectName}` : "");
}

/**
 * Handle the unlink button click event
 */
function handleUnlinkClick(): void {
    store.clearChanges();
    store.removeActivity(props.activity);
    store.isSingleUnlinkOpen = true;
}

const activityLink = window.Vue.computed(() => {
    if (props.activity._links === null) {
        return undefined;
    }

    if (props.canModify && !store.isStudentView && props.activity._links.status !== null) {
        return `${props.activity._links.status}?returnUrl=${props.returnUrl}`;
    }

    if (props.activity._links?.summary !== null) {
        return `${props.activity._links.summary}?returnUrl=${props.returnUrl}`;
    }

    return undefined;
});
</script>

<template>
    <div class="card small-12">
        <h3>
            <span :class="'icon-' + kebabCase(activity.assessmentType)"></span>
            <component :is="activity._links !== null ? 'a' : 'span'" class="title" :href="activityLink">
                {{ activity.title }}
            </component>
        </h3>
        <p class="meta">
            {{ getActivityMeta(activity) }}
            <template v-if="activity.dueAt && props.canModify && !store.isStudentView">
                |
                <span :class="getGradient(props.activity.completionCount, props.activity.studentCount)"
                    >{{ props.activity.completionCount }}/{{ props.activity.studentCount }} students completed</span
                >
            </template>
        </p>
        <p v-if="activity.dueAt" class="meta" :title="activity.dueAt.long">
            Due <time :datetime="activity.dueAt.atom || undefined">{{ activity.dueAt.relative }}</time>
        </p>
    </div>
    <nav v-if="props.canModify">
        <a class="no-text" title="Unlink Assessment" @click.prevent="handleUnlinkClick()"
            ><sbx-icon name="close"></sbx-icon
        ></a>
    </nav>
    <nav v-else-if="activity.progressTick !== null" class="progress-tick-container">
        <progress-tick
            :progress-tick="activity.progressTick"
            :can-modify="true"
            :is-self-assessable="activity.isSelfAssessableTask"
            :transitions="{
                incomplete: 'selfAssessed',
                selfAssessed: 'incomplete',
                teacherAssessed: 'incomplete',
            }"
            @update:class="(newClass) => (activity.progressTick!.progressClass = newClass)"
        />
    </nav>
</template>

<style scoped>
nav.progress-tick-container {
    margin-right: 5px;
    margin-top: 5px;
}
</style>
