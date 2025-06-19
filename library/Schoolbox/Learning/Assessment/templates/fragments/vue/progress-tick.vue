<script setup lang="ts">
/**
 * This mimics the rendering logic of progressTick.twig
 * It still requires jquery.progressTickForm behind the scenes to actually work though...
 */

import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";

interface Props {
    progressTick: Activity["progressTick"];
    /**
     * Whether the user can modify the progress tick
     * When true, will still check for the postRouteUrl to determine if the form should be rendered
     * Defaults to true
     */
    canModify?: boolean;
    /**
     * Whether the relevant activity is self-assessable
     * Defaults to false
     */
    isSelfAssessable?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canModify: true,
    isSelfAssessable: false,
});

const postRouteUrl = window.Vue.computed(() => {
    const route = props.progressTick?.postRouteUrl ?? "";
    return route.length > 0 ? route : undefined;
});

const progressClass = window.Vue.computed(() => props.progressTick?.progressClass ?? undefined);

/**
 * A string to be used as the value of the 'data-progress-tick-status' attribute
 */
const progressTickStatus = window.Vue.computed(() => {
    if (props.progressTick === null) {
        return undefined;
    }

    switch (progressClass.value) {
        case "incomplete":
            return "incomplete";
        case "pending":
            return "selfAssessed";
        case "reviewed":
            return "teacherAssessed";
        default:
            return undefined;
    }
});

/**
 * A description to be used as mouseover text
 */
const mouseoverTitle = window.Vue.computed(() => {
    if (props.progressTick === null) {
        return undefined;
    }

    switch (props.progressTick.progressClass) {
        case "incomplete":
            return "Incomplete";
        case "pending":
            return postRouteUrl.value ? "Self-assessed" : "Awaiting Feedback";
        case "failed":
            return postRouteUrl.value ? undefined : "Resubmission Required";
        case "reviewed":
            return "Completed";
        default:
            return undefined;
    }
});
</script>

<template>
    <template v-if="props.progressTick === null"></template>
    <form
        v-else-if="postRouteUrl && props.canModify"
        :action="postRouteUrl"
        method="post"
        class="progress-tick-form"
        :data-progress-tick-form="props.isSelfAssessable ? 'selfAssessed' : 'teacherAssessed'"
    >
        <a
            class="no-text progress-tick"
            :class="progressClass"
            :data-progress-tick-status="progressTickStatus"
            :title="mouseoverTitle"
        ></a>
    </form>
    <span v-else-if="progressClass" class="no-text progress-tick" :class="progressClass" :title="mouseoverTitle"></span>
</template>

<style scoped></style>
