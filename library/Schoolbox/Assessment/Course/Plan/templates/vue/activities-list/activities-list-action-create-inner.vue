<script setup lang="ts">
/**
 * The inner contents for a create activity dropdown
 * Should be wrapped in a ul
 * sbx-pop-out is not created here, as it may need to be customised by the parent component
 */
import { getReturnUrl } from "@js/util/sbx/urlUtils";

interface Props {
    contentId: string;
    createRoute: string;
    ltiAssessmentsEnabled: boolean;
}

const props = defineProps<Props>();

const activityTypeLocale = {
    project: window.sbProps.l10n.get("project"),
    dueWork: window.sbProps.l10n.get("dueWork"),
};
</script>

<template>
    <ul :id="props.contentId" class="f-dropdown" data-dropdown-content>
        <li>
            <a :href="getReturnUrl(`${props.createRoute}?type=project`)" class="icon-project">
                {{ activityTypeLocale.project.proper() }}
            </a>
        </li>
        <li>
            <a :href="getReturnUrl(`${props.createRoute}?type=dueWork`)" class="icon-due-work">
                {{ activityTypeLocale.dueWork.proper() }}
            </a>
        </li>
        <li>
            <a :href="getReturnUrl(`${props.createRoute}?type=quiz`)" class="icon-quiz">Quiz</a>
        </li>
        <li>
            <a :href="getReturnUrl(`${props.createRoute}?type=task`)" class="icon-task">Task</a>
        </li>
        <li v-if="props.ltiAssessmentsEnabled">
            <a :href="getReturnUrl(`${props.createRoute}?type=lti`)" class="icon-lti">3rd Party Assessment</a>
        </li>
        <li>
            <a :href="getReturnUrl(`${props.createRoute}?type=lessonPlan`)" class="icon-lesson-plan">Lesson Plan</a>
        </li>
    </ul>
</template>

<style scoped></style>
