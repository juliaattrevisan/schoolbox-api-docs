<script setup lang="ts">
/**
 * The inner contents for a create activity dropdown
 * Should be wrapped in a ul
 * sbx-pop-out is not created here, as it may need to be customised by the parent component
 */
import { sbProps } from "@js/util/sbx/pageUtils";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import { ACTIVITY_TYPE_ICON } from "@schoolboxAssessment/Types/assessmentTypes";

interface Props {
    contentId: string;
    createRoute: string;
}

const props = defineProps<Props>();
</script>

<template>
    <ul :id="props.contentId" class="f-dropdown" data-dropdown-content>
        <li v-for="(label, activityType) in sbProps.assessmentTypes" :key="activityType">
            <a
                :class="ACTIVITY_TYPE_ICON[activityType]"
                :href="getReturnUrl(`${props.createRoute}?type=${activityType}`)"
                :data-test="`unit-planner-create-${activityType}-${$props.contentId}`"
                >{{ label }}
            </a>
        </li>
    </ul>
</template>

<style scoped></style>
