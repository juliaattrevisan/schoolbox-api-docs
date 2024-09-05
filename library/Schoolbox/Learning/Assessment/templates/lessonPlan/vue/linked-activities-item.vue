<script setup lang="ts">
import { kebabCase } from "lodash";
import { getGradient } from "@js/util/cssUtils";
import { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
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

/**
 * Get the colour gradient class for the activity, that helps indicate the
 * completion status of the current activity
 * @returns The urgency class
 */
function getUrgency(): string {
    return store.gradeGradientEnable
        ? getGradient(props.activity.completionCount, props.activity.studentCount)
        : "gradient-none";
}

/**
 * This is intended to match the behaviour in progressTick.twig.
 * @param activity The activity whose progressTick we want to describe.
 * @returns A description to be used as mouseover text.
 */
function getProgressTickMouseoverTitle(activity: Activity): string {
    const { progressTick } = activity; // This syntax means the same as 'progressTick = activity.progressTick;'

    if (progressTick === null) {
        throw new Error("This function should not have been called.");
    }

    const postRouteUrlSupplied = progressTick.postRouteUrl !== "";

    switch (progressTick.progressClass) {
        case "incomplete":
            return "Incomplete";
        case "pending":
            return postRouteUrlSupplied ? "Self-assessed" : "Awaiting Feedback";
        case "failed":
            return postRouteUrlSupplied ? "N/A" : "Resubmission Required";
        case "reviewed":
            return "Completed";
        default:
            return "";
    }
}

/**
 * This is intended to match the behaviour in progressTick.twig.
 * @param activity The relevant activity.
 * @returns A string to be used as the value of the 'data-progress-tick-status' attribute.
 */
function getDataProgressTickStatus(activity: Activity): string {
    const { progressTick } = activity; // This syntax means the same as 'progressTick = activity.progressTick;'

    if (progressTick === null) {
        throw new Error("This function should not have been called.");
    }

    switch (progressTick.progressClass) {
        case "incomplete":
            return "incomplete";
        case "pending":
            return "selfAssessed";
        case "reviewed":
            return "teacherAssessed";
        default:
            return "";
    }
}

/**
 * This is intended to match the behaviour in progressTick.twig.
 * @param activity The activity whose progressTick we want to describe
 * @returns A description to be used as mouseover text
 */
function getDataProgressTickForm(activity: Activity): string {
    return activity.isSelfAssessableTask ? "selfAssessed" : "teacherAssessed";
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
                <span :class="getUrgency()"
                    >{{ props.activity.completionCount }}/{{ props.activity.studentCount }} students completed</span
                >
            </template>
        </p>
        <p v-if="activity.dueAt" class="meta" :title="activity.dueAt.long">
            Due <time :datetime="activity.dueAt.atom || undefined">{{ activity.dueAt.relative }}</time>
        </p>
    </div>
    <nav v-if="props.canModify">
        <a class="icon-close no-text" title="Unlink Assessment" @click.prevent="handleUnlinkClick()"></a>
    </nav>
    <nav v-else-if="activity.progressTick !== null" class="progress-tick-container">
        <form
            v-if="activity.progressTick.postRouteUrl !== ''"
            :action="activity.progressTick.postRouteUrl || undefined"
            method="post"
            class="progress-tick-form"
            :data-progress-tick-form="getDataProgressTickForm(activity)"
        >
            <a
                :class="'no-text progress-tick ' + activity.progressTick.progressClass"
                :data-progress-tick-status="getDataProgressTickStatus(activity)"
                :title="getProgressTickMouseoverTitle(activity)"
            ></a>
        </form>
        <span
            v-else-if="activity.progressTick.progressClass !== ''"
            :class="'no-text progress-tick ' + activity.progressTick.progressClass"
            :title="getProgressTickMouseoverTitle(activity)"
        ></span>
    </nav>
</template>

<style scoped>
nav.progress-tick-container {
    margin-right: 5px;
    margin-top: 5px;
}
</style>
