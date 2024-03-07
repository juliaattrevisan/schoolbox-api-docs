<script setup lang="ts">
import { kebabCase } from "lodash";
import { Activity } from "@schoolboxAssessment/Types/assessmentTypes";

interface Props {
    activity: Activity;
    dropdownContainerEl: HTMLElement;
}

const props = defineProps<Props>();

/**
 * Get the current url; these redirects are used after backend processing
 * so you can return to your current location afterwards
 *
 * Use the base64encoded version of the url's pathname, so it can work in
 * conjunection with Class Redirect in the back end
 * @param {string} url - The url to go to
 * @returns {string} - The url with the returnUrl parameter appended
 */
function returnUrl(url: string): string {
    return `${url}?returnUrl=${window.btoa(window.location.pathname)}`;
}

const activityLink = window.Vue.computed(() => {
    // If the activity is a project, use the status link
    if (props.activity.assessmentType === "project") {
        return props.activity._links.status;
    }
    // If the modifyContent link exists, use it - This is the case for quizzes
    if (props.activity._links.modifyContent) {
        return props.activity._links.modifyContent;
    }
    // Otherwise, use the summary link
    return props.activity._links.summary;
});
</script>

<template>
    <li class="actions-small-1">
        <div class="list-item">
            <div class="card small-12 compact wrap-down">
                <a :href="returnUrl(activityLink)">
                    <span :class="'icon-' + kebabCase(props.activity.assessmentType)"></span>
                    <p>{{ props.activity.title }}</p>
                </a>
            </div>
        </div>
        <nav>
            <sbx-pop-out :content-id="'dropdown-unitplanner-activity-' + props.activity.id"></sbx-pop-out>
        </nav>
        <!-- Move the dropdown content to the unit planner table so that it can get around the cell's overflow:hidden -->
        <ul
            :id="'dropdown-unitplanner-activity-' + props.activity.id"
            class="f-dropdown"
            data-dropdown-content
            data-dropdown-move-to="#unitplanner-dropdown-container"
        >
            <li>
                <a :href="returnUrl(props.activity._links.modify)" class="icon-modify">{{
                    props.activity.assessmentType === "quiz" ? "Modify Settings" : "Modify"
                }}</a>
            </li>
            <li v-if="props.activity.assessmentType === 'quiz' && props.activity._links.modifyContent">
                <a :href="returnUrl(props.activity._links.modifyContent)" class="icon-modify">Modify Contents</a>
            </li>
        </ul>
    </li>
</template>

<style scoped>
.card.compact {
    padding-right: 0;
}

.card.compact > * {
    height: 2.5rem;
}

.card.compact a p {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    min-width: 0;
}
</style>
