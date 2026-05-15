<script setup lang="ts">
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import {
    ACTIVITY_TYPE_DIALOG_AGENT,
    ACTIVITY_TYPE_DUE_WORK,
    ACTIVITY_TYPE_QUIZ,
    ACTIVITY_TYPE_REPORTING,
} from "@schoolboxAssessment/Types/assessmentTypes";
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
        <a :href="getReturnUrl(props.activity._links.summary)" class="sbx-button--has-icon"
            ><sbx-icon name="view"></sbx-icon>View</a
        >
    </li>
    <li v-if="viewStatusLink" :data-test="`markbook-assessment-dropdown-${props.activity.id}-view-status`">
        <a :href="viewStatusLink" class="sbx-button--has-icon"><sbx-icon name="progress"></sbx-icon>View Status</a>
    </li>
    <li
        v-if="viewStudentParticipationLink"
        :data-test="`markbook-assessment-dropdown-${props.activity.id}-student-participation`"
    >
        <a :href="viewStudentParticipationLink" class="sbx-button--has-icon"
            ><sbx-icon name="group"></sbx-icon>Student Participation</a
        >
    </li>
    <li v-if="props.activity._links?.modify" :data-test="`markbook-assessment-dropdown-${props.activity.id}-modify`">
        <a :href="getReturnUrl(props.activity._links.modify)" class="sbx-button--has-icon"
            ><sbx-icon name="modify"></sbx-icon
            >{{
                props.activity.assessmentType === ACTIVITY_TYPE_QUIZ ||
                props.activity.assessmentType === ACTIVITY_TYPE_DIALOG_AGENT
                    ? "Modify Settings"
                    : "Modify"
            }}</a
        >
    </li>
    <li
        v-if="props.activity._links?.modifyContent"
        :data-test="`markbook-assessment-dropdown-${props.activity.id}-modify-content`"
    >
        <a :href="getReturnUrl(props.activity._links.modifyContent)" class="sbx-button--has-icon"
            ><sbx-icon name="modify"></sbx-icon>Modify Content</a
        >
    </li>
    <li
        v-if="props.activity._links?.configureAgent"
        :data-test="`markbook-assessment-dropdown-${props.activity.id}-configure-agent`"
    >
        <a :href="getReturnUrl(props.activity._links.configureAgent)" class="sbx-button--has-icon"
            ><sbx-icon name="modify"></sbx-icon>Configure Agent</a
        >
    </li>
    <li
        v-if="
            viewStatusLink &&
            (props.activity.assessmentType === ACTIVITY_TYPE_DUE_WORK ||
                props.activity.assessmentType === ACTIVITY_TYPE_REPORTING)
        "
        :data-test="`markbook-assessment-dropdown-${props.activity.id}-import-feedback`"
    >
        <a :href="`${viewStatusLink}&importFeedback`" class="sbx-button--has-icon"
            ><sbx-icon name="upload"></sbx-icon>Import Feedback</a
        >
    </li>

    <!-- Additional check for whether all impls are writable before opening the bulk publish -->
    <template
        v-if="
            props.activity.assessmentType === ACTIVITY_TYPE_DUE_WORK &&
            activity._routes.postFeedbacks &&
            mbStore.isActivityWritable(props.activity)
        "
    >
        <li class="separator">Bulk Actions</li>
        <li :data-test="`markbook-assessment-dropdown-${props.activity.id}-publish`">
            <mb-table-head-activity-publish :activity="props.activity" />
        </li>
    </template>
</template>

<style scoped></style>
