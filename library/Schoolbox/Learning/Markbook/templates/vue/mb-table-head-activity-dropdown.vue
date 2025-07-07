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
</script>

<template>
    <li class="separator">Activity</li>
    <li v-if="props.activity._links?.summary">
        <a :href="getReturnUrl(props.activity._links.summary)" class="icon-view">View</a>
    </li>
    <li v-if="props.activity._links?.modify">
        <a :href="getReturnUrl(props.activity._links.modify)" class="icon-modify">
            {{ props.activity.assessmentType === ACTIVITY_TYPE_QUIZ ? "Modify Settings" : "Modify" }}
        </a>
    </li>
    <li v-if="props.activity._links?.modifyContent">
        <a :href="getReturnUrl(props.activity._links.modifyContent)" class="icon-modify">Modify Content</a>
    </li>
    <li v-if="viewStatusLink">
        <a :href="viewStatusLink" class="icon-progress">View Status</a>
    </li>
    <li v-if="props.activity.assessmentType === ACTIVITY_TYPE_DUE_WORK && viewStatusLink">
        <a :href="`${viewStatusLink}&importFeedback`" class="icon-upload">Import Feedback</a>
    </li>
    <template v-if="props.activity.assessmentType === ACTIVITY_TYPE_DUE_WORK && activity._routes.postFeedbacks">
        <li class="separator">Bulk Actions</li>
        <li>
            <mb-table-head-activity-publish :activity="props.activity" />
        </li>
    </template>
</template>

<style scoped></style>
