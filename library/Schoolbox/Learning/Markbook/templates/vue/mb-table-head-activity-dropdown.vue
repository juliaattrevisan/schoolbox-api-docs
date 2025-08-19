<script setup lang="ts">
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import { ACTIVITY_TYPE_DUE_WORK, ACTIVITY_TYPE_QUIZ } from "@schoolboxAssessment/Types/assessmentTypes";
import type { MarkbookActivity } from "./types/payload";
import { useMarkbookStore } from "./store/markbookStore";
import MbTableHeadActivityPublish from "./mb-table-head-activity-publish.vue";

interface Props {
    activity: MarkbookActivity;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const viewStatusLink = window.Vue.computed(() => {
    if (props.activity._links?.status && mbStore.data) {
        return `${getReturnUrl(props.activity._links.status)}&${mbStore.data.filtersQueryArgs}`;
    }
    return null;
});

const viewStudentParticipationLink = window.Vue.computed(() => {
    if (props.activity._links?.studentParticipation && mbStore.data) {
        return `${getReturnUrl(props.activity._links.studentParticipation)}&${mbStore.data.filtersQueryArgs}`;
    }
    return null;
});
</script>

<template>
    <li class="separator">Activity</li>
    <li v-if="props.activity._links?.summary" :data-test="`markbook-assessment-dropdown-${props.activity.id}-view`">
        <a :href="getReturnUrl(props.activity._links.summary)" class="icon-view">View</a>
    </li>
    <li v-if="viewStatusLink" :data-test="`markbook-assessment-dropdown-${props.activity.id}-view-status`">
        <a :href="viewStatusLink" class="icon-progress">View Status</a>
    </li>
    <li
        v-if="viewStudentParticipationLink"
        :data-test="`markbook-assessment-dropdown-${props.activity.id}-student-participation`"
    >
        <a :href="viewStudentParticipationLink" class="icon-group">Student Participation</a>
    </li>
    <li v-if="props.activity._links?.modify" :data-test="`markbook-assessment-dropdown-${props.activity.id}-modify`">
        <a :href="getReturnUrl(props.activity._links.modify)" class="icon-modify">
            {{ props.activity.assessmentType === ACTIVITY_TYPE_QUIZ ? "Modify Settings" : "Modify" }}
        </a>
    </li>
    <li
        v-if="props.activity._links?.modifyContent"
        :data-test="`markbook-assessment-dropdown-${props.activity.id}-modify-content`"
    >
        <a :href="getReturnUrl(props.activity._links.modifyContent)" class="icon-modify">Modify Content</a>
    </li>
    <li
        v-if="props.activity.assessmentType === ACTIVITY_TYPE_DUE_WORK && viewStatusLink"
        :data-test="`markbook-assessment-dropdown-${props.activity.id}-import-feedback`"
    >
        <a :href="`${viewStatusLink}&importFeedback`" class="icon-upload">Import Feedback</a>
    </li>
    <template v-if="props.activity.assessmentType === ACTIVITY_TYPE_DUE_WORK && activity._routes.postFeedbacks">
        <li class="separator">Bulk Actions</li>
        <li :data-test="`markbook-assessment-dropdown-${props.activity.id}-publish`">
            <mb-table-head-activity-publish :activity="props.activity" />
        </li>
    </template>
</template>

<style scoped></style>
