<script setup lang="ts">
/**
 * This mimics the rendering and form logic of progressTick.twig/jquery.progressTickForm.js
 */

import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";

/**
 * Transitions
 * key: old status => value: new status
 */
const TRANSITIONS = {
    incomplete: "teacherAssessed",
    selfAssessed: "teacherAssessed",
    teacherAssessed: "incomplete",
} as const;

/**
 * Form submit/return values for each config type
 */
const STATUS_VALUES = {
    incomplete: 1,
    selfAssessed: 2,
    teacherAssessed: 3,
} as const;

/**
 * Convert a transition status to a progress class
 * This is mainly here since props.progressTick only gives us a class
 */
const STATUS_TO_CLASS: Record<keyof typeof TRANSITIONS, typeof progressClass.value> = {
    incomplete: "incomplete",
    selfAssessed: "pending",
    teacherAssessed: "reviewed",
} as const;

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
    /**
     * To turn off the margin created by progress-tick-form, defaults to false
     */
    noMargin?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canModify: true,
    isSelfAssessable: false,
    noMargin: false,
});

const emit = defineEmits<{
    (e: "update:class", progressClass: NonNullable<Props["progressTick"]>["progressClass"]): void;
}>();

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
        return "incomplete";
    }

    switch (progressClass.value) {
        case "incomplete":
            return "incomplete";
        case "pending":
            return "selfAssessed";
        case "reviewed":
            return "teacherAssessed";
        default:
            return "incomplete";
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

/** To disable the form for aesthetic reasons  */
const isSubmitting = window.Vue.ref(false);

/**
 * When the progress tick is clicked, rotate the status
 */
async function onTickClicked(): Promise<void> {
    if (props.progressTick === null || !props.canModify || postRouteUrl.value === undefined) {
        return;
    }

    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;

    const nextStatus = TRANSITIONS[progressTickStatus.value];
    const response = await fetch(postRouteUrl.value, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ status: STATUS_VALUES[nextStatus] }),
    });

    isSubmitting.value = false;

    if (!response.ok) {
        console.error("Failed to update progress tick status");
        window.sbProps.toastActivate("").timeout({
            text: "Failed to update progress tick status",
            isSuccess: false,
        });
        return;
    }

    emit("update:class", STATUS_TO_CLASS[nextStatus] ?? null);
}
</script>

<template>
    <template v-if="props.progressTick === null"></template>
    <div
        v-else-if="postRouteUrl && props.canModify"
        class="progress-tick-form"
        :style="{ margin: props.noMargin ? '0' : undefined }"
        :disabled="isSubmitting || undefined"
    >
        <a
            class="no-text progress-tick"
            :class="progressClass"
            :style="{ margin: props.noMargin ? '0' : undefined }"
            :title="mouseoverTitle"
            @click.prevent="onTickClicked"
        ></a>
    </div>
    <span
        v-else-if="progressClass"
        class="no-text progress-tick"
        :class="progressClass"
        :style="{ margin: props.noMargin ? '0' : undefined }"
        :title="mouseoverTitle"
    ></span>
</template>

<style scoped></style>
