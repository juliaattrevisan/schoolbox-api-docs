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
 * Get the colour gradient class for the activity, that helps indicate the
 * completion status of the current activity
 * @returns {string} The urgency class
 */
function getUrgency() {
    return store.gradeGradientEnable
        ? getGradient(props.activity.completionCount, props.activity.studentCount)
        : "gradient-none";
}

/**
 * This is intended to match the behaviour in progressTick.twig.
 * @param { Activity } activity The activity whose progressTick we want to describe.
 * @returns { string } A description to be used as mouseover text.
 */
function getProgressTickMouseoverTitle(activity: Activity) {
    const { progressTick } = activity; // This syntax means the same as 'progressTick = activity.progressTick;'

    if (progressTick === null) {
        throw new Error("This function should not have been called.");
    }

    const progressStatus = progressTick.progress;
    const postUrlSupplied = progressTick.url !== null;

    switch (progressStatus) {
        case "incomplete":
            return "Incomplete";
        case "pending":
            return postUrlSupplied ? "Self-assessed" : "Awaiting Feedback";
        case "failed":
            return postUrlSupplied ? "N/A" : "Resubmission Required";
        case "reviewed":
            return "Completed";
        default:
            return "";
    }
}

/**
 * This is intended to match the behaviour in progressTick.twig.
 * @param { Activity } activity The relevant activity.
 * @returns { string } A string to be used as the value of the 'data-progress-tick-status' attribute.
 */
function getDataProgressTickStatus(activity: Activity) {
    const { progressTick } = activity; // This syntax means the same as 'progressTick = activity.progressTick;'

    if (progressTick === null) {
        throw new Error("This function should not have been called.");
    }

    const progressStatus = progressTick.progress;

    switch (progressStatus) {
        case "incomplete":
            return "incomplete";
        case "pending":
            return "selfAssessed";
        case "failed":
            return "failed";
        case "reviewed":
            return "teacherAssessed";
        default:
            return "";
    }
}

/**
 * This is intended to match the behaviour in progressTick.twig.
 * @param { Activity } activity The activity whose progressTick we want to describe
 * @returns { string } A description to be used as mouseover text
 */
function getDataProgressTickForm(activity: Activity) {
    return activity.isSelfAssessableTask ? "selfAssessed" : "teacherAssessed";
}
</script>

<template>
    <div class="card small-12">
        <h3>
            <span :class="'icon-' + kebabCase(activity.assessmentType)"></span>
            <a
                v-if="activity._links !== null"
                class="title"
                :href="(props.isInstructor && !store.isStudentView) ? activity._links.status + '?returnUrl=' + returnUrl : activity._links.summary + '?returnUrl=' + returnUrl"
                >{{ activity.title }}</a
            >
            <span v-else class="title">{{ activity.title }}</span>
        </h3>
        <p class="meta">
            {{ getActivityMeta(activity) }}
            <template v-if="activity.dueAtFormatted && props.isInstructor && !store.isStudentView">
                |
                <span :class="getUrgency()"
                    >{{ props.activity.completionCount }}/{{ props.activity.studentCount }} students completed</span
                >
            </template>
        </p>
        <p v-if="activity.dueAtFormatted" class="meta">Due {{ activity.dueAtFormatted }}</p>
    </div>
    <nav v-if="activity.progressTick !== null" class="progress-tick-container">
        <form
            v-if="activity.progressTick.url !== null"
            :action="activity.progressTick.url"
            method="post"
            class="progress-tick-form"
            :data-progress-tick-form="getDataProgressTickForm(activity)"
        >
            <a
                :class="
                    'no-text progress-tick ' +
                    (activity.progressTick.progress === 'incomplete' ? '' : activity.progressTick.progress)
                "
                :data-progress-tick-status="getDataProgressTickStatus(activity)"
                :title="getProgressTickMouseoverTitle(activity)"
            ></a>
        </form>
        <a
            v-else-if="activity._links !== null"
            :href="activity._links.status"
            :class="'no-text progress-tick ' + activity.progressTick.progress"
            :title="getProgressTickMouseoverTitle(activity)"
        ></a>
    </nav>
    <nav v-if="isInstructor">
        <a class="icon-close no-text" title="Unlink Assessment" @click.prevent="handleUnlinkClick()"></a>
    </nav>
</template>

<style scoped>
nav.progress-tick-container {
    margin-right: 40px;
    margin-top: 5px;
}
</style>
