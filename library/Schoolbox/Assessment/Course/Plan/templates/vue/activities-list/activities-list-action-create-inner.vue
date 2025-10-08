<script setup lang="ts">
/**
 * The inner contents for a create activity dropdown
 * Should be wrapped in a ul
 * sbx-pop-out is not created here, as it may need to be customised by the parent component
 */
import locale from "@js/terminology/rosettaStone";
import { getReturnUrl } from "@js/util/sbx/urlUtils";

interface Props {
    contentId: string;
    createRoute: string;
    ltiAssessmentsEnabled: boolean;
}

const props = defineProps<Props>();
</script>

<template>
    <ul :id="props.contentId" class="f-dropdown" data-dropdown-content>
        <li>
            <a
                :href="getReturnUrl(`${props.createRoute}?type=project`)"
                class="icon-project"
                :data-test="`unit-planner-create-project-${props.contentId}`"
            >
                {{ locale.project.proper() }}
            </a>
        </li>
        <li>
            <a
                :href="getReturnUrl(`${props.createRoute}?type=dueWork`)"
                class="icon-due-work"
                :data-test="`unit-planner-create-due-work-${props.contentId}`"
            >
                {{ locale.dueWork.proper() }}
            </a>
        </li>
        <li>
            <a
                :href="getReturnUrl(`${props.createRoute}?type=quiz`)"
                class="icon-quiz"
                :data-test="`unit-planner-create-quiz-${props.contentId}`"
                >Quiz</a
            >
        </li>
        <li>
            <a
                :href="getReturnUrl(`${props.createRoute}?type=task`)"
                class="icon-task"
                :data-test="`unit-planner-create-task-${props.contentId}`"
                >Task</a
            >
        </li>
        <li v-if="props.ltiAssessmentsEnabled">
            <a :href="getReturnUrl(`${props.createRoute}?type=lti`)" class="icon-lti">3rd Party Assessment</a>
        </li>
        <li>
            <a
                :href="getReturnUrl(`${props.createRoute}?type=lessonPlan`)"
                class="icon-lesson-plan"
                :data-test="`unit-planner-create-lesson-plan-${props.contentId}`"
            >
                {{ locale.lessonPlan.proper() }}
            </a>
        </li>
    </ul>
</template>

<style scoped></style>
