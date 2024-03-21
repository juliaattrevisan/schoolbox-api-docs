<script setup lang="ts">
// The inner contents for a create activity dropdown, should be wrapped in a ul
// sbx-pop-out is not created here, as it may need to be customised by the parent component

import { getReturnUrl } from "@js/util/sbx/urlUtils";
import { useUnitPlannerStore } from "./store/unitPlannerStore";

interface Props {
    contentId: string;
    createUrl: string;
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();
</script>

<template>
    <ul :id="props.contentId" class="f-dropdown" data-dropdown-content>
        <li>
            <a :href="getReturnUrl(`${props.createUrl}?type=project`)" class="icon-project">{{
                store.locale?.project.proper || "Project"
            }}</a>
        </li>
        <li>
            <a :href="getReturnUrl(`${props.createUrl}?type=dueWork`)" class="icon-due-work">{{
                store.locale?.dueWork.proper || "Due Work"
            }}</a>
        </li>
        <li>
            <a :href="getReturnUrl(`${props.createUrl}?type=quiz`)" class="icon-quiz">Quiz</a>
        </li>
        <li>
            <a :href="getReturnUrl(`${props.createUrl}?type=task`)" class="icon-task">Task</a>
        </li>
        <li>
            <a :href="getReturnUrl(`${props.createUrl}?type=lti`)" class="icon-lti">3rd Party Assessment</a>
        </li>
        <li>
            <a :href="getReturnUrl(`${props.createUrl}?type=lessonPlan`)" class="icon-lesson-plan">Lesson Plan</a>
        </li>
    </ul>
</template>

<style scoped></style>
