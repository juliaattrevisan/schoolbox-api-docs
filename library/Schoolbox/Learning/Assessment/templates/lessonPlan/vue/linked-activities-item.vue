<script setup lang="ts">
import { kebabCase } from "lodash";
import { useLinkedActivitiesStore } from "./store/linkedActivitiesStore";
import { CourseAssessments, Activity } from "./linkedActivitiesTypes";

interface Props {
    activity: Activity;
    courseAssessments: CourseAssessments;
    isInstructor: boolean;
    returnUrl: string;
}

const props = defineProps<Props>();

const store = useLinkedActivitiesStore();

/**
 * Get the title of the course that the activity belongs to
 * Will also include the project title if the activity is part of a project
 * @param { Activity } activity The activity to get the course title for
 * @returns { string } Course title with project title if applicable
 */
function getActivityCourseTitle(activity: Activity): string {
    const selectedUnit = props.courseAssessments.units.find((unit) =>
        unit.assessments.some(
            (assessment) =>
                assessment.id === activity.id || (assessment.assessments && assessment.assessments[activity.id])
        )
    );

    const selectedProject =
        selectedUnit &&
        selectedUnit.assessments.find((assessment) => assessment.assessments && assessment.assessments[activity.id]);

    return (selectedUnit ? selectedUnit.title : "") + (selectedProject ? ` | ${selectedProject.title}` : "");
}

/**
 * Handle the unlink button click event
 */
function handleUnlinkClick() {
    store.clearChanges();
    store.removeActivity(props.activity);
    store.isSingleUnlinkOpen = true;
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
            {{ getActivityCourseTitle(activity) }}
        </p>
        <p class="meta">Due {{ activity.closeAtFormatted }}</p>
    </div>
    <nav v-if="isInstructor">
        <a class="icon-unlink no-text" title="Unlink Assessment" @click.prevent="handleUnlinkClick()"></a>
    </nav>
</template>

<style scoped></style>
